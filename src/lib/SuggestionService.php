<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\DTO\SuggestionEnrichment;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\FieldFormat;
use Masilia\AiAssistant\FieldFormatResolver;
use Masilia\AiAssistant\SystemPromptContext;

/**
 * Prepares system and user prompts for AI suggestion requests.
 *
 * Handles enrichment (sibling fields, matrix context, content context),
 * translation prompt building, and delegates to AiPromptBuilder.
 */
readonly class SuggestionService
{
    public function __construct(
        private AiPromptBuilder       $promptBuilder,
        private FieldFormatResolver   $formatResolver,
        private FieldContextExtractor $contextExtractor,
        private LanguageNormalizer    $languageNormalizer,
    )
    {
    }

    /**
     * @return array{systemPrompt: string, userPrompt: string, format: FieldFormat}
     */
    public function prepare(AiSuggestRequest $aiRequest): array
    {
        $enrichment = $this->extractEnrichment($aiRequest);

        return [
            'systemPrompt' => $this->buildSystemPrompt($aiRequest, $enrichment),
            'userPrompt' => $this->buildUserPrompt($aiRequest, $enrichment),
            'format' => $enrichment->format,
        ];
    }

    private function extractEnrichment(AiSuggestRequest $aiRequest): SuggestionEnrichment
    {
        $normalizedLanguage = $this->languageNormalizer->normalize($aiRequest->language);

        $enriched = $this->contextExtractor->extractFromContent($aiRequest, $normalizedLanguage);

        $siblingFields = array_map(
            static fn(SiblingField $f) => $f->toArray(),
            $enriched['siblingFields']
        );

        if (empty($siblingFields) && !empty($aiRequest->siblingFields)) {
            $siblingFields = $aiRequest->siblingFields;
        }

        $matrixContext = null;
        if ($aiRequest->fieldType === FieldType::EZMATRIX && $aiRequest->contentId > 0) {
            $matrixContext = $this->contextExtractor->extractMatrixContextForRequest($aiRequest, $normalizedLanguage);
        }

        return new SuggestionEnrichment(
            normalizedLanguage: $normalizedLanguage,
            contentType: $enriched['contentType'],
            contentTitle: $enriched['contentTitle'],
            siblingFields: $siblingFields,
            matrixContext: $matrixContext,
            format: $this->formatResolver->resolve($aiRequest->fieldType),
        );
    }

    private function buildSystemPrompt(AiSuggestRequest $aiRequest, SuggestionEnrichment $enrichment): string
    {
        return $this->promptBuilder->buildSystemPrompt(
            new SystemPromptContext(
                format: $enrichment->format,
                fieldName: $aiRequest->fieldName,
                contentType: $enrichment->contentType,
                language: $enrichment->normalizedLanguage,
                contentTitle: $enrichment->contentTitle,
                siblingFields: $enrichment->siblingFields,
                fieldType: $aiRequest->fieldType,
                subFieldKey: $aiRequest->subFieldKey,
                metaKeys: $aiRequest->metaKeys,
            ),
            $this->languageNormalizer,
            $enrichment->matrixContext,
        );
    }

    private function buildUserPrompt(AiSuggestRequest $aiRequest, SuggestionEnrichment $enrichment): string
    {
        $userPromptText = $aiRequest->prompt;
        $currentValue = $aiRequest->currentValue;

        if ($aiRequest->sourceLanguage === '') {
            return $this->promptBuilder->enrichUserPrompt($userPromptText, $currentValue);
        }

        $normalizedSourceLang = $this->languageNormalizer->normalize($aiRequest->sourceLanguage);
        $sourceValue = $this->contextExtractor->getFieldValueInLanguage(
            $aiRequest,
            $normalizedSourceLang,
            $enrichment->normalizedLanguage,
        );

        if ($sourceValue === null || $sourceValue->value === '') {
            return $this->promptBuilder->enrichUserPrompt($userPromptText, $currentValue);
        }

        $userPromptText = $aiRequest->fieldType === FieldType::EZMATRIX
            ? $this->translationMatrixPrompt($normalizedSourceLang, $enrichment->normalizedLanguage, $sourceValue->value)
            : $this->translationTextPrompt($normalizedSourceLang, $enrichment->normalizedLanguage, $sourceValue->value);

        return $this->promptBuilder->enrichUserPrompt($userPromptText, '');
    }

    private function translationMatrixPrompt(string $from, string $to, string $sourceValue): string
    {
        return sprintf(
            "Translate each cell of the following matrix from %s to %s. "
            . "Output ONLY a JSON object with shape {\"rows\": [{\"cells\": {<col_id>: \"<translated_value>\"}}, ...]}. "
            . "Preserve the original row order. Plain text only in each cell.\n\n%s",
            $from,
            $to,
            $sourceValue
        );
    }

    private function translationTextPrompt(string $from, string $to, string $sourceValue): string
    {
        return sprintf(
            "Translate the following %s content to %s. Only output the translated text, "
            . "nothing else. Preserve the tone and style of the original.\n\n%s",
            $from,
            $to,
            $sourceValue
        );
    }
}
