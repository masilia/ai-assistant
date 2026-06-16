<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

/**
 * Parses raw LLM text responses into structured PHP data.
 *
 * Handles JSON extraction from responses that may contain
 * surrounding text or markdown formatting.
 */
final class LlmResponseParser
{

    /**
     * Parse the LLM response into structured intent + parameters.
     *
     * @return array{intent: string, parameters: array}|null
     */
    public function parseIntentResponse(string $response): ?array
    {
        $json = $this->extractJson($response);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['intent'])) {
            return null;
        }

        $intent = $decoded['intent'];
        if (!in_array($intent, IntentName::all(), true)) {
            return null;
        }

        return [
            'intent' => $intent,
            'parameters' => $decoded['parameters'] ?? [],
        ];
    }

    /**
     * Extract the first complete JSON object from a text response.
     */
    public function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        for ($i = $start, $iMax = strlen($text); $i < $iMax; $i++) {
            if ($text[$i] === '{') {
                $depth++;
            } elseif ($text[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
