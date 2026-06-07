<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;

/**
 * Builds NovaSEO (novaseometas) system prompts: the whole-block JSON-schema
 * prompt and the per-meta single-value prompt.
 *
 * Encapsulates all SEO-specific knowledge (meta field discovery, hints and
 * character limits) so the generic {@see AiPromptBuilder} stays format-agnostic.
 */
class NovaSeoPromptBuilder
{
    public const FIELD_TYPE = 'novaseometas';

    /**
     * Recommended (not hard-cap) character limits per meta key. Single source
     * of truth, shared by both the schema hints and the single-field prompts.
     */
    private const RECOMMENDED_CHAR_LIMITS = [
        'title' => 60,
        'description' => 160,
    ];

    /**
     * Hardcoded default schema used when the config resolver is unavailable
     * (e.g. unit tests) or the Novactive eZ SEO bundle is not installed.
     *
     * @var array<string, array{label: string, maxLength?: int|null}>
     */
    private const FALLBACK_META_FIELDS = [
        'title' => ['label' => 'Title'],
        'description' => ['label' => 'Description'],
        'keywords' => ['label' => 'Keywords'],
        'canonical' => ['label' => 'External Canonical URL'],
        'type' => ['label' => 'Type'],
        'og:title' => ['label' => 'Open Graph Title'],
        'og:description' => ['label' => 'Open Graph Description'],
        'og:image' => ['label' => 'Open Graph Image URL'],
        'og:image:alt' => ['label' => 'Open Graph Image Alt'],
        'twitter:title' => ['label' => 'Twitter Title'],
        'twitter:description' => ['label' => 'Twitter Description'],
        'twitter:image' => ['label' => 'Twitter Image URL'],
    ];

    private ?ConfigResolverInterface $configResolver;

    public function __construct(?ConfigResolverInterface $configResolver = null)
    {
        $this->configResolver = $configResolver;
    }

    /**
     * Whole-block prompt: instructs the model to return a JSON object whose
     * keys are the editable, AI-eligible metas.
     *
     * @param string   $base     The shared context/intro produced by AiPromptBuilder.
     * @param string[] $metaKeys Explicit set of editable, AI-eligible meta keys.
     *                           When provided, the schema is restricted to these
     *                           keys so it matches the UI exactly.
     */
    public function wholeBlockPrompt(string $base, array $metaKeys = []): string
    {
        $metaFields = $this->getTextMetaFields();

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

    /**
     * Read the Novactive eZ SEO bundle's `fieldtype_metas` config and return
     * only the free-text fields (i.e. not `select`/`boolean`/etc.). Falls back
     * to {@see FALLBACK_META_FIELDS} when the config is unavailable.
     *
     * @return array<string, array{label: string, maxLength?: int|null, type?: string}>
     */
    private function getTextMetaFields(): array
    {
        if ($this->configResolver === null) {
            return self::FALLBACK_META_FIELDS;
        }

        try {
            $config = $this->configResolver->getParameter('fieldtype_metas', 'nova_ezseo');
        } catch (\Throwable) {
            return self::FALLBACK_META_FIELDS;
        }

        if (!is_array($config) || $config === []) {
            return self::FALLBACK_META_FIELDS;
        }

        $textFields = [];
        foreach ($config as $key => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $type = $meta['type'] ?? 'text';
            if ($type !== 'text') {
                continue;
            }
            $textFields[(string)$key] = [
                'label' => (string)($meta['label'] ?? $key),
                'maxLength' => isset($meta['maxLength']) ? (int)$meta['maxLength'] : null,
                'type' => $type,
            ];
        }

        return $textFields !== [] ? $textFields : self::FALLBACK_META_FIELDS;
    }

    private function fieldHint(string $key, ?int $maxLength): string
    {
        // Prefer the SEO-recommended length (single source of truth, shared with
        // the single-field prompts) over the field's raw storage cap, falling
        // back to the configured maxLength for any other field.
        $charLimit = $this->defaultCharLimit($key) ?? $maxLength;

        $description = match (true) {
            $key === 'title' => 'compelling, click-worthy SEO Meta Title',
            $key === 'description' => 'concise, engaging Meta Description summarizing the content',
            $key === 'keywords' => '5-8 relevant, search-optimized keywords or keyphrases (comma-separated list)',
            $key === 'canonical' => 'absolute canonical URL (leave empty if not applicable)',
            $key === 'type' => 'content type (e.g. website, article)',
            str_starts_with($key, 'og:title') => 'Open Graph title',
            str_starts_with($key, 'og:description') => 'Open Graph description',
            str_starts_with($key, 'og:image') => 'Open Graph image URL (leave empty if not applicable)',
            str_starts_with($key, 'twitter:title') => 'Twitter card title',
            str_starts_with($key, 'twitter:description') => 'Twitter card description',
            str_starts_with($key, 'twitter:image') => 'Twitter image URL (leave empty if not applicable)',
            default => 'metadata value',
        };

        if ($charLimit !== null) {
            return sprintf('%s, under %d characters', $description, $charLimit);
        }

        return $description;
    }

    private function defaultCharLimit(string $key): ?int
    {
        return match (true) {
            $key === 'title', str_ends_with($key, ':title') => self::RECOMMENDED_CHAR_LIMITS['title'],
            $key === 'description', str_ends_with($key, ':description') => self::RECOMMENDED_CHAR_LIMITS['description'],
            default => null,
        };
    }
}
