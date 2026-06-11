<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

/**
 * Format-specific rules appended to the AI system prompt.
 *
 * Centralises what used to be a 4-arm `match` in
 * {@see AiPromptBuilder::buildSystemPrompt()}. The per-format string
 * is the suffix appended after the shared "You are a professional
 * content writing assistant…" base. Each arm is intentionally
 * compact; longer-form matrix / SEO rules live in their own builders
 * (see {@see MatrixPromptBuilder} patterns in
 * {@see NovaSeoPromptBuilder} and `AiPromptBuilder::matrixPrompt`).
 */
final class FormatPromptRules
{
    /**
     * Rule suffix that every {@see for()} return value ends with —
     * extracted so a future change to the closing line (e.g. for
     * voice / branding) only has to happen in one place.
     */
    private const TAIL = "\n- Tailor the content specifically to the context provided above.";

    public static function for(FieldFormat $format): string
    {
        return "\n\nRules:" . match ($format) {
                FieldFormat::PLAIN_TEXT => "\n- Output ONLY plain text, single line.\n- No HTML tags, no markdown formatting, no line breaks.\n- Be concise and direct.",

                FieldFormat::TEXT_BLOCK => "\n- Output ONLY plain text.\n- Line breaks are allowed for paragraphs.\n- No HTML tags, no markdown formatting.\n- Write in a clear, structured manner.",

                FieldFormat::HTML => "\n- Output clean, semantic HTML.\n- Use ONLY these tags: " . FieldFormat::HTML_ALLOWED_TAGS . ".\n- Do NOT use <h1> (reserved for page title).\n- Do NOT use <div>, classes, IDs, inline styles, or scripts.\n- Do NOT wrap output in ```html code blocks or any markdown.\n- Do NOT include <!DOCTYPE>, <html>, <head>, or <body> tags.\n- Output ONLY the inner HTML content, starting directly with content tags.",

                FieldFormat::JSON => "\n- Output ONLY a valid raw JSON object.\n- Do NOT wrap the JSON in markdown code blocks.\n- No extra keys, no trailing text.",
            } . self::TAIL;
    }
}
