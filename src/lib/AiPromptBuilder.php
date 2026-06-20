<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Masilia\AiAssistant\Field\FieldType;

class AiPromptBuilder
{
    private NovaSeoPromptBuilder $novaSeo;

    public function __construct(
        ?NovaSeoPromptBuilder $novaSeo = null,
    )
    {
        $this->novaSeo = $novaSeo ?? new NovaSeoPromptBuilder();
    }

    /**
     * Build the system prompt for an AI suggestion request.
     *
     * @param SystemPromptContext $ctx
     * @param LanguageNormalizer|null $languageNormalizer
     * @param array{headers: array<string,string>, rowCount: int}|null $matrixContext
     *        When set, the field is an ezmatrix and the prompt is augmented
     *        with matrix-specific rules and the column header list.
     * @return string
     */
    public function buildSystemPrompt(
        SystemPromptContext $ctx,
        ?LanguageNormalizer $languageNormalizer = null,
        ?array              $matrixContext = null,
    ): string
    {
        $subFieldKey = $this->resolveSubFieldKey($ctx);
        $normalizedLanguage = $this->normalizeLanguage($ctx->language, $languageNormalizer);

        $context = $this->buildContextString($ctx, $normalizedLanguage);
        $contentContext = $this->buildContentContext($ctx);

        $base = "You are a professional content writing assistant for a CMS.$context$contentContext";

        if ($ctx->fieldType === FieldType::NOVASEOMETAS && $subFieldKey === '') {
            return $this->novaSeo->wholeBlockPrompt($base, $ctx->metaKeys);
        }

        if ($ctx->fieldType === FieldType::NOVASEOMETAS && $subFieldKey !== '') {
            return $this->novaSeo->subFieldPrompt($base, $subFieldKey);
        }

        if ($ctx->fieldType === FieldType::EZMATRIX && $matrixContext !== null) {
            return $this->matrixPrompt($base, $matrixContext);
        }

        if ($ctx->fieldType === FieldType::EZIMAGE) {
            return $this->ezimagePrompt($base);
        }

        return $base . FormatPromptRules::for($ctx->format);
    }

    private function resolveSubFieldKey(SystemPromptContext $ctx): string
    {
        $subFieldKey = $ctx->subFieldKey;
        if ($subFieldKey === '' && $ctx->fieldName !== '' && str_starts_with(strtolower($ctx->fieldName), 'meta:')) {
            $subFieldKey = trim(substr($ctx->fieldName, strlen('meta:')));
        }

        return strtolower($subFieldKey);
    }

    private function normalizeLanguage(string $language, ?LanguageNormalizer $normalizer): string
    {
        if ($normalizer !== null) {
            return $normalizer->normalize($language);
        }

        return $language;
    }

    private function buildContextString(SystemPromptContext $ctx, string $normalizedLanguage): string
    {
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

        return $context;
    }

    private function buildContentContext(SystemPromptContext $ctx): string
    {
        $contentContext = '';

        if ($ctx->contentTitle !== '') {
            $contentContext .= "\nContent title: \"" . AiConstants::scrubForPrompt($ctx->contentTitle) . "\".";
        }

        if (!empty($ctx->siblingFields)) {
            $contentContext .= "\nOther fields already filled in this content item (use for context, do not repeat):";
            foreach ($ctx->siblingFields as $field) {
                $label = AiConstants::scrubForPrompt($field['label'] ?? '');
                $value = AiConstants::scrubForPrompt(mb_substr($field['value'] ?? '', 0, AiConstants::MAX_SIBLING_CHARS));
                if ($label !== '' && $value !== '') {
                    $contentContext .= "\n  - $label: \"$value\"";
                }
            }
        }

        if ($contentContext !== '') {
            $contentContext = "\n\nContent context:" . $contentContext;
        }

        return $contentContext;
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
        $rowCount = max(1, (int)($matrixContext['rowCount'] ?? 0));

        $columnLines = '';
        foreach ($headers as $colId => $colName) {
            // Put the identifier prominently (it's the JSON key the AI
            // must use) and relegate the display name to a parenthetical
            // hint. The previous "Display Name (\"id\")" ordering made the
            // AI use the display name (often uppercased by CSS) as the
            // key, which then failed to match the lowercase DOM identifier.
            $columnLines .= "\n  - key: \"" . AiConstants::scrubForPrompt((string)$colId) . "\" (human label: " . AiConstants::scrubForPrompt((string)$colName) . ")";
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

    /**
     * Build the system prompt for an ezimage alt text field.
     */
    private function ezimagePrompt(string $base): string
    {
        return $base
            . "\n\nAlt text generation rules:"
            . "\n- Generate concise, descriptive alt text for screen readers."
            . "\n- Keep it under " . AiConstants::MAX_ALT_TEXT_CHARS . " characters."
            . "\n- Be specific and factual about the image content."
            . "\n- Do not start with \"Image of\" or \"Photo of\"."
            . "\n- Focus on what the image shows, not its style."
            . "\n- Output ONLY the alt text, no quotes, no commentary."
            . "\n- Plain text only. No HTML.";
    }

    public function enrichUserPrompt(string $userPrompt, string $currentValue = ''): string
    {
        if (empty($currentValue)) {
            return $userPrompt;
        }

        $truncated = AiConstants::truncate($currentValue, AiConstants::MAX_CURRENT_VALUE_CHARS);

        return "$userPrompt\n\nCurrent content for context (do not repeat it unless asked):\n\"\"\"$truncated\"\"\"";
    }
}
