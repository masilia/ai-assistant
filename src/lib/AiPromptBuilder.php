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
     * Build the system prompt for an AI suggestion request.
     *
     * @param string[]|array<int,array{label: string, value: string}> $siblingFields
     *        The shape is forgiving: either a list of SiblingField::toArray() arrays
     *        (label + value) or a list of plain strings. Empty entries are skipped.
     * @param string[] $metaKeys     Explicit set of editable, AI-eligible meta
     *                               keys for a novaseometas whole-block request.
     * @param array{headers: array<string,string>, rowCount: int}|null $matrixContext
     *        When set, the field is an ezmatrix and the prompt is augmented
     *        with matrix-specific rules and the column header list.
     */
    public function buildSystemPrompt(
        SystemPromptContext $ctx,
        ?LanguageNormalizer $languageNormalizer = null,
        ?array $matrixContext = null,
    ): string {
        // Resolve the SEO meta key explicitly. Fall back to deriving it from the
        // legacy "Meta: <key>" display label only when no explicit key is given.
        $subFieldKey = $ctx->subFieldKey;
        if ($subFieldKey === '' && $ctx->fieldName !== '' && str_starts_with(strtolower($ctx->fieldName), 'meta:')) {
            $subFieldKey = trim(substr($ctx->fieldName, strlen('meta:')));
        }
        $subFieldKey = strtolower($subFieldKey);

        $normalizedLanguage = $ctx->language;
        if ($languageNormalizer !== null) {
            $normalizedLanguage = $languageNormalizer->normalize($ctx->language);
        }

        $context = '';

        if ($ctx->contentType) {
            $context .= " The content type is \"{$ctx->contentType}\".";
        }
        if ($ctx->fieldName) {
            $context .= " You are writing for the field \"{$ctx->fieldName}\".";
        }
        if ($normalizedLanguage && $normalizedLanguage !== 'en') {
            $context .= " Write in language code: {$normalizedLanguage}.";
        }

        $contentContext = '';

        if ($ctx->contentTitle !== '') {
            $contentContext .= "\nContent title: \"" . $this->escape($ctx->contentTitle) . "\".";
        }

        if (!empty($ctx->siblingFields)) {
            $contentContext .= "\nOther fields already filled in this content item (use for context, do not repeat):";
            foreach ($ctx->siblingFields as $field) {
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

        if ($ctx->fieldType === NovaSeoPromptBuilder::FIELD_TYPE && $subFieldKey === '') {
            return $this->novaSeo->wholeBlockPrompt($base, $ctx->metaKeys);
        }

        if ($subFieldKey !== '') {
            return $this->novaSeo->subFieldPrompt($base, $subFieldKey);
        }

        if ($ctx->fieldType === 'ezmatrix' && $matrixContext !== null) {
            return $this->matrixPrompt($base, $matrixContext);
        }

        return match ($ctx->format) {
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

    /**
     * Build the system prompt for an ezmatrix field. The output JSON shape
     * is fixed: {"rows": [{"cells": {<col_id>: "<value>"}}, ...]}.
     * The column identifier list and target row count come from
     * $matrixContext (extracted by {@see FieldContextExtractor::extractMatrixContext}).
     *
     * @param array{headers: array<string,string>, rowCount: int} $matrixContext
     */
    private function matrixPrompt(string $base, array $matrixContext): string
    {
        $headers = $matrixContext['headers'] ?? [];
        $rowCount = max(1, (int) ($matrixContext['rowCount'] ?? 0));

        $columnLines = '';
        foreach ($headers as $colId => $colName) {
            // Put the identifier prominently (it's the JSON key the AI
            // must use) and relegate the display name to a parenthetical
            // hint. The previous "Display Name (\"id\")" ordering made the
            // AI use the display name (often uppercased by CSS) as the
            // key, which then failed to match the lowercase DOM identifier.
            $columnLines .= "\n  - key: \"" . $this->escape((string) $colId) . "\" (human label: " . $this->escape((string) $colName) . ")";
        }
        if ($columnLines === '') {
            $columnLines = "\n  - (no columns defined; use a single \"value\" key)";
        }

        return $base
            . "\n\nMatrix generation rules:"
            . "\n- The output is a JSON object with shape: {\"rows\": [{\"cells\": {<col_id>: \"<value>\"}}, ...]}."
            . "\n- Use the EXACT lowercase identifier (the value of the 'key' field below) inside each row's \"cells\" object. Do NOT use the human label, do NOT change case:"
            . $columnLines
            . "\n- The output must contain " . $rowCount . " row(s) (match the existing row count)."
            . "\n- Preserve the original row order."
            . "\n- Each cell value is plain text only. No HTML."
            . "\n- Output ONLY the raw JSON, no markdown code fences, no commentary.";
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
