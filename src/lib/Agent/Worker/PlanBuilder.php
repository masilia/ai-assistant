<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Worker;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Block\ContentCatalog;

/**
 * Validates and constructs a Plan from orchestrator tool arguments.
 *
 * The orchestrator LLM calls `propose_plan(...)` with raw arguments.
 * This worker validates the structure and returns a typed `Plan`.
 *
 * Three-layer validation:
 *   1. Structural validation (Plan::validate()) — intent, title,
 *      siteaccess, parent_location_id, etc.
 *   2. Schema validation (validateBlockSchemas()) — block fields match
 *      the actual content type schema, especially matrix column names.
 *   3. Required-field validation (validateRequiredFields()) — required
 *      fields are not empty.
 *
 * No default-block suggestion: the LLM is the source of truth for which
 * blocks to use. create_content plans with empty `blocks` are accepted and
 * produce content with no blocks — the LLM should always propose its own
 * layout based on the explore_site output.
 */
final class PlanBuilder
{
    public function __construct(
        private readonly ?BlockCatalog $blockCatalog = null,
        private readonly ?ContentCatalog $contentCatalog = null,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments Raw arguments from the LLM's `propose_plan` call
     */
    public function build(array $arguments): Plan
    {
        $plan = Plan::fromArray($arguments);

        $error = $plan->validate();
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $schemaError = $this->validateBlockSchemas($plan);
        if ($schemaError !== null) {
            throw new \InvalidArgumentException($schemaError);
        }

        $requiredError = $this->validateRequiredFields($plan);
        if ($requiredError !== null) {
            throw new \InvalidArgumentException($requiredError);
        }

        return $plan;
    }

    /**
     * Same as build() — kept for backward compatibility. Exploration
     * result is ignored: the LLM always proposes its own block layout.
     *
     * @param array<string, mixed> $arguments
     */
    public function buildWithDefaults(array $arguments, ?ExplorationResult $exploration = null): Plan
    {
        return $this->build($arguments);
    }

    /**
     * Validate field structure against actual content type schemas.
     *
     * Currently checks matrix field column identifiers (the most common
     * LLM mistake — guessing column names that don't exist on the
     * content type). Unknown content types are skipped (lenient) so the
     * LLM can still propose plans for types the catalog doesn't know.
     */
    private function validateBlockSchemas(Plan $plan): ?string
    {
        if ($plan->intent !== Plan::INTENT_CREATE_CONTENT) {
            return null;
        }
        if ($plan->contentType === null || $plan->contentType === '') {
            return null;
        }

        $schema = $this->resolveContentTypeSchema($plan->contentType);
        if ($schema === null) {
            return null;
        }

        foreach ($schema['fields'] as $fieldId => $fieldInfo) {
            $fieldType = (string) ($fieldInfo['type'] ?? '');
            if ($fieldType !== 'ezmatrix') {
                continue;
            }

            $validColumns = array_column($fieldInfo['columns'] ?? [], 'identifier');
            if ($validColumns === []) {
                continue;
            }

            $value = $plan->fields[$fieldId] ?? null;
            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $rowIdx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach (array_keys($row) as $colKey) {
                    if (!in_array($colKey, $validColumns, true)) {
                        return sprintf(
                            'Content type "%s": matrix field "%s" row %d has unknown column "%s". Valid columns: %s',
                            $plan->contentType,
                            $fieldId,
                            $rowIdx,
                            $colKey,
                            implode(', ', $validColumns),
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Validate that required fields are not empty.
     *
     * Catches the most common LLM mistake: submitting a plan where
     * a required field is `""`, `null`, or missing entirely. Without
     * this check, Ibexa's `ContentService::createContent` throws
     * `ContentFieldValidationException` AFTER user approval — which
     * is exactly the failure mode we want to avoid.
     *
     * Empty values considered invalid:
     *   - null
     *   - empty string `""` (also whitespace-only)
     *   - empty array `[]`
     *   - array whose entries are all empty (recursively)
     */
    private function validateRequiredFields(Plan $plan): ?string
    {
        if ($plan->intent !== Plan::INTENT_CREATE_CONTENT) {
            return null;
        }
        if ($plan->contentType === null || $plan->contentType === '') {
            return null;
        }

        $schema = $this->resolveContentTypeSchema($plan->contentType);
        if ($schema === null) {
            return null;
        }

        foreach ($schema['fields'] as $fieldId => $fieldInfo) {
            if (!($fieldInfo['required'] ?? false)) {
                continue;
            }

            $value = $plan->fields[$fieldId] ?? null;
            if (!$this->isValueEmpty($value)) {
                continue;
            }

            return sprintf(
                'Content type "%s": required field "%s" is empty. Provide a non-empty value.',
                $plan->contentType,
                $fieldId,
            );
        }

        return null;
    }

    private function isValueEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            if ($value === []) {
                return true;
            }
            foreach ($value as $entry) {
                if (!$this->isValueEmpty($entry)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Resolve a content type schema from BlockCatalog (block types) or
     * ContentCatalog (standard types like page, article, folder, etc.).
     */
    private function resolveContentTypeSchema(string $identifier): ?array
    {
        return $this->blockCatalog?->getBlockSchema($identifier)
            ?? $this->contentCatalog?->getContentTypeSchema($identifier);
    }
}
