<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

class AiPromptBuilder
{
    public function buildSystemPrompt(
        FieldFormat        $format,
        string             $fieldName = '',
        string             $contentType = '',
        string             $language = 'en',
        string             $contentTitle = '',
        array              $siblingFields = [],
        LanguageNormalizer $languageNormalizer = null,
    ): string
    {
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

        return match ($format) {
            FieldFormat::PLAIN_TEXT => "$base\n\nRules:\n- Output ONLY plain text, single line.\n- No HTML tags, no markdown formatting, no line breaks.\n- Be concise and direct.\n- Tailor the content specifically to the context provided above.",

            FieldFormat::TEXT_BLOCK => "$base\n\nRules:\n- Output ONLY plain text.\n- Line breaks are allowed for paragraphs.\n- No HTML tags, no markdown formatting.\n- Write in a clear, structured manner.\n- Tailor the content specifically to the context provided above.",

            FieldFormat::HTML => "$base\n\nRules:\n- Output clean, semantic HTML.\n- Use ONLY these tags: <p>, <h2>, <h3>, <h4>, <h5>, <h6>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <tr>, <td>, <th>, <thead>, <tbody>, <blockquote>.\n- Do NOT use <h1> (reserved for page title).\n- Do NOT use <div>, classes, IDs, inline styles, or scripts.\n- Do NOT wrap output in ```html code blocks or any markdown.\n- Do NOT include <!DOCTYPE>, <html>, <head>, or <body> tags.\n- Output ONLY the inner HTML content, starting directly with content tags.\n- Tailor the content specifically to the context provided above.",
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
