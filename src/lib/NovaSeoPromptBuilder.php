<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Seo\FallbackSeoMetaFieldsProvider;
use Masilia\AiAssistant\Seo\SeoMetaFieldsProviderInterface;

/**
 * Builds NovaSEO (novaseometas) system prompts: the whole-block JSON-schema
 * prompt and the per-meta single-value prompt.
 *
 * Encapsulates all SEO-specific knowledge (meta field discovery, hints and
 * character limits) so the generic {@see AiPromptBuilder} stays format-agnostic.
 *
 * The actual list of available meta fields is supplied by a
 * {@see SeoMetaFieldsProviderInterface}, allowing the host application to
 * plug in any SEO bundle (e.g. novactive/ezseoscorebundle) without coupling
 * the lib to that bundle.
 */
class NovaSeoPromptBuilder
{
    public const FIELD_TYPE = FieldType::NOVASEOMETAS;

    /**
     * Recommended (not hard-cap) character limits per meta key. Single source
     * of truth, shared by both the schema hints and the single-field prompts.
     */
    private const RECOMMENDED_CHAR_LIMITS = [
        'title' => 60,
        'description' => 160,
    ];

    /**
     * Exact-match hints for well-known SEO meta keys.
     *
     * @var array<string, string>
     */
    private const KEY_HINTS = [
        'title' => 'compelling, click-worthy SEO Meta Title',
        'description' => 'concise, engaging Meta Description summarizing the content',
        'keywords' => '5-8 relevant, search-optimized keywords or keyphrases (comma-separated list)',
        'canonical' => 'absolute canonical URL (leave empty if not applicable)',
        'type' => 'content type (e.g. website, article)',
    ];

    /**
     * Prefix-based hints for og: and twitter: sub-fields. Evaluated in
     * order; the first matching prefix wins.
     *
     * @var array<string, string>
     */
    private const KEY_HINT_PREFIXES = [
        'og:title' => 'Open Graph title',
        'og:description' => 'Open Graph description',
        'og:image' => 'Open Graph image URL (leave empty if not applicable)',
        'twitter:title' => 'Twitter card title',
        'twitter:description' => 'Twitter card description',
        'twitter:image' => 'Twitter image URL (leave empty if not applicable)',
    ];

    private SeoMetaFieldsProviderInterface $fieldsProvider;

    public function __construct(?SeoMetaFieldsProviderInterface $fieldsProvider = null)
    {
        $this->fieldsProvider = $fieldsProvider ?? new FallbackSeoMetaFieldsProvider();
    }

    /**
     * Whole-block prompt: instructs the model to return a JSON object whose
     * keys are the editable, AI-eligible metas.
     *
     * @param string $base The shared context/intro produced by AiPromptBuilder.
     * @param string[] $metaKeys Explicit set of editable, AI-eligible meta keys.
     *                           When provided, the schema is restricted to these
     *                           keys so it matches the UI exactly.
     */
    public function wholeBlockPrompt(string $base, array $metaKeys = []): string
    {
        $metaFields = $this->fieldsProvider->getTextMetaFields();

        if ($metaKeys !== []) {
            $allowed = array_map('strtolower', $metaKeys);
            $filtered = array_filter(
                $metaFields,
                static fn($key) => in_array(strtolower((string)$key), $allowed, true),
                ARRAY_FILTER_USE_KEY
            );
            // Ignore an empty intersection and fall back to the full set, so a
            // mismatched key list never produces an empty schema.
            if ($filtered !== []) {
                $metaFields = $filtered;
            }
        }

        $schemaLines = [];
        foreach ($metaFields as $key => $meta) {
            $label = $meta['label'] ?? $key;
            $hint = $this->fieldHint((string)$key, $meta['maxLength'] ?? null);
            $schemaLines[] = sprintf('  "%s": "%s (%s)"', $key, $label, $hint);
        }
        $schema = $schemaLines !== [] ? implode(",\n", $schemaLines) : '  "title": "Meta Title"';

        $seoRules = "You are a professional SEO content assistant. Generate SEO metadata for this content item.

            Output MUST be a valid JSON object matching this schema:
            {
            $schema
            }

            Rules:
            - Output ONLY the raw JSON object.
            - Do NOT wrap the JSON in markdown code blocks (e.g. no ```json).
            - Include ONLY the keys listed in the schema above. Do not add extra keys.
            - If a field is not applicable to the content (e.g. og:image, twitter:image), set it to an empty string.
            - Tailor the values specifically to the content context provided below.";

        return "$base\n\n$seoRules";
    }

    private function fieldHint(string $key, ?int $maxLength): string
    {
        $charLimit = $this->defaultCharLimit($key) ?? $maxLength;
        $description = self::KEY_HINTS[$key] ?? $this->resolvePrefixHint($key);

        if ($charLimit !== null) {
            return sprintf('%s, under %d characters', $description, $charLimit);
        }

        return $description;
    }

    private function defaultCharLimit(string $key): ?int
    {
        foreach (self::RECOMMENDED_CHAR_LIMITS as $suffix => $limit) {
            if ($key === $suffix || str_ends_with($key, ':' . $suffix)) {
                return $limit;
            }
        }

        return null;
    }

    private function resolvePrefixHint(string $key): string
    {
        foreach (self::KEY_HINT_PREFIXES as $prefix => $hint) {
            if (str_starts_with($key, $prefix)) {
                return $hint;
            }
        }

        return 'metadata value';
    }

    /**
     * Single-value prompt for one meta key (e.g. "title", "description").
     */
    public function subFieldPrompt(string $base, string $subFieldKey): string
    {
        $titleLimit = self::RECOMMENDED_CHAR_LIMITS['title'];
        $descriptionLimit = self::RECOMMENDED_CHAR_LIMITS['description'];

        $seoRules = match ($subFieldKey) {
            'title' => "- Generate a compelling, click-worthy, and SEO-friendly Meta Title.\n- Strictly keep it under {$titleLimit} characters.\n- Primary keywords should be placed near the beginning of the title.\n- Do not repeat the content title identically unless requested.",
            'description' => "- Generate a concise, engaging Meta Description summarizing the content.\n- Keep it under {$descriptionLimit} characters.\n- Include a subtle call-to-action or value proposition.\n- Do not use quotes or special characters.",
            'keywords' => "- Generate a list of 5-8 relevant, search-optimized keywords or keyphrases.\n- Return them ONLY as a comma-separated list (e.g. keyword1, keyword2, keyword3).\n- Do not add any introductory or concluding text, bullet points, or numbering.",
            default => "- Keep the metadata concise, relevant, and search-optimized.",
        };

        return "$base\n\nRules:\n$seoRules\n- Output ONLY the generated metadata, nothing else.\n- Return plain text only — NO JSON, NO markdown code blocks, NO quotes around the value, NO \"value:\" or \"content:\" prefixes.\n- No HTML tags, no line breaks.";
    }
}
