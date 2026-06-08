<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

class AiPromptBuilder
{
    private NovaSeoPromptBuilder $novaSeo;

    public function __construct(
        ?NovaSeoPromptBuilder $novaSeo = null,
    ) {
        $this->novaSeo = $novaSeo ?? new NovaSeoPromptBuilder();
    }

    /**
     * @param string[] $siblingFields
     * @param string[] $metaKeys     Explicit set of editable, AI-eligible meta
     *                               keys for a novaseometas whole-block request.
     *                               When provided, the JSON schema is restricted
     *                               to these keys so it matches the UI exactly.
     */
    public function buildSystemPrompt(
        FieldFormat        $format,
        string             $fieldName = '',
        string             $contentType = '',
        string             $language = 'en',
        string             $contentTitle = '',
        array              $siblingFields = [],
        LanguageNormalizer $languageNormalizer = null,
        string             $fieldType = '',
        string             $subFieldKey = '',
        array              $metaKeys = [],
    ): string
    {
        // Resolve the SEO meta key explicitly. Fall back to deriving it from the
        // legacy "Meta: <key>" display label only when no explicit key is given.
        if ($subFieldKey === '' && $fieldName !== '' && str_starts_with(strtolower($fieldName), 'meta:')) {
            $subFieldKey = trim(substr($fieldName, strlen('meta:')));
        }
        $subFieldKey = strtolower($subFieldKey);
        $normalizedLanguage = $language;
        if ($languageNormalizer !== null) {
            $normalizedLanguage = $languageNormalizer->normalize($language);
        }

        $context = '';

        if ($contentType) {
            $context .= " The content type is \"$contentType\".";
        }
        if ($fieldName) {
            $context .= " You are writing for the field \"$fieldName\".";
        }
        if ($normalizedLanguage && $normalizedLanguage !== 'en') {
            $context .= " Write in language code: $normalizedLanguage.";
        }

        $contentContext = '';

        if ($contentTitle !== '') {
            $contentContext .= "\nContent title: \"" . $this->escape($contentTitle) . "\".";
        }

        if (!empty($siblingFields)) {
            $contentContext .= "\nOther fields already filled in this content item (use for context, do not repeat):";
            foreach ($siblingFields as $field) {
                $label = $this->escape($field['label'] ?? '');
                $value = $this->escape(mb_substr($field['value'] ?? '', 0, AiConstants::MAX_SIBLING_CHARS));
                if ($label !== '' && $value !== '') {
                    $contentContext .= "\n  - $label: \"$value\"";
                }
            }
        }

        if ($contentContext !== '') {
            $contentContext = "\n\nContent context:" . $contentContext;
        }

        $base = "You are a professional content writing assistant for a CMS.$context$contentContext";

        if ($fieldType === NovaSeoPromptBuilder::FIELD_TYPE && $subFieldKey === '') {
            return $this->novaSeo->wholeBlockPrompt($base, $metaKeys);
        }

        if ($subFieldKey !== '') {
            return $this->novaSeo->subFieldPrompt($base, $subFieldKey);
        }

        return match ($format) {
            FieldFormat::PLAIN_TEXT => "$base\n\nRules:\n- Output ONLY plain text, single line.\n- No HTML tags, no markdown formatting, no line breaks.\n- Be concise and direct.\n- Tailor the content specifically to the context provided above.",

            FieldFormat::TEXT_BLOCK => "$base\n\nRules:\n- Output ONLY plain text.\n- Line breaks are allowed for paragraphs.\n- No HTML tags, no markdown formatting.\n- Write in a clear, structured manner.\n- Tailor the content specifically to the context provided above.",

            FieldFormat::HTML => "$base\n\nRules:\n- Output clean, semantic HTML.\n- Use ONLY these tags: <p>, <h2>, <h3>, <h4>, <h5>, <h6>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <tr>, <td>, <th>, <thead>, <tbody>, <blockquote>.\n- Do NOT use <h1> (reserved for page title).\n- Do NOT use <div>, classes, IDs, inline styles, or scripts.\n- Do NOT wrap output in ```html code blocks or any markdown.\n- Do NOT include <!DOCTYPE>, <html>, <head>, or <body> tags.\n- Output ONLY the inner HTML content, starting directly with content tags.\n- Tailor the content specifically to the context provided above.",

            FieldFormat::JSON => "$base\n\nRules:\n- Output ONLY a valid raw JSON object.\n- Do NOT wrap the JSON in markdown code blocks.\n- No extra keys, no trailing text.\n- Tailor the values specifically to the context provided above.",
        };
    }

    private function escape(string $value): string
    {
        return str_replace(['"', "\n", "\r"], ['\\"', ' ', ''], $value);
    }

    public function enrichUserPrompt(string $userPrompt, string $currentValue = ''): string
    {
        if (empty($currentValue)) {
            return $userPrompt;
        }

        $truncated = mb_strlen($currentValue) > AiConstants::MAX_CURRENT_VALUE_CHARS
            ? mb_substr($currentValue, 0, AiConstants::MAX_CURRENT_VALUE_CHARS) . '...'
            : $currentValue;

        return "$userPrompt\n\nCurrent content for context (do not repeat it unless asked):\n\"\"\"$truncated\"\"\"";
    }
}
