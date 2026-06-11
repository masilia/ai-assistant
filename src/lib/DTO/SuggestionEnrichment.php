<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

use Masilia\AiAssistant\FieldFormat;

/**
 * Resolved context for an AI suggestion request, computed once and
 * consumed by both the system-prompt and user-prompt builders in
 * {@see \Masilia\Bundle\AiAssistant\Controller\AiSuggestController::prepareSuggestion()}.
 *
 * Splits the "what do we know about this request" precomputation (a
 * single {@see \Masilia\AiAssistant\FieldContextExtractor::extractFromContent()}
 * call + matrix context decision) from the "how do we render it" step
 * (the two prompt builders). Both halves become testable in isolation.
 */
final readonly class SuggestionEnrichment
{
    /**
     * @param array<int, array{label: string, value: string}> $siblingFields
     * @param array{headers: array<string,string>, rowCount: int}|null $matrixContext
     */
    public function __construct(
        public string     $normalizedLanguage,
        public string     $contentType,
        public string     $contentTitle,
        public array      $siblingFields,
        public ?array     $matrixContext,
        public FieldFormat $format,
    ) {
    }
}
