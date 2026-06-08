<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Dispatches {@see FieldValueStringifierInterface::toString()} by field-type
 * identifier. Mirrors the tagged-iterator registry pattern used by the
 * provider-adapter system and the app's FieldValueTransformer.
 *
 * When no specific stringifier is registered for a field type, delegates to
 * the {@see GenericStringifier} (which must be registered with type `_fallback`).
 *
 * Stringifier exceptions are caught and logged (warning level) so a misbehaving
 * custom stringifier cannot crash the whole AI prompt pipeline, but operators
 * can still see what went wrong.
 */
class FieldValueStringifierRegistry
{
    /** @var array<string, FieldValueStringifierInterface> */
    private array $map = [];

    private ?FieldValueStringifierInterface $fallback = null;

    private LoggerInterface $logger;

    /**
     * @param iterable<FieldValueStringifierInterface> $stringifiers
     * @param LoggerInterface                          $aiLogger
     *        Channel-scoped logger (injected via $aiLogger parameter name
     *        binding in services.yaml). Defaults to NullLogger if absent
     *        so unit tests don't have to wire a logger.
     */
    public function __construct(iterable $stringifiers, ?LoggerInterface $aiLogger = null)
    {
        $this->logger = $aiLogger ?? new NullLogger();

        foreach ($stringifiers as $stringifier) {
            foreach ($stringifier::getSupportedFieldTypes() as $type) {
                if ($type === '_fallback') {
                    $this->fallback = $stringifier;
                    continue;
                }
                $this->map[$type] = $stringifier;
            }
        }
    }

    /**
     * Converts a field value to a plain-text string for AI context.
     *
     * Returns '' when neither a specific nor a fallback stringifier can
     * produce output. Failures are logged at warning level.
     */
    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $type = $fieldDefinition->fieldTypeIdentifier;
        $stringifier = $this->map[$type] ?? $this->fallback;

        if ($stringifier === null) {
            return '';
        }

        try {
            return $stringifier->toString($field, $fieldDefinition);
        } catch (Throwable $e) {
            $this->logger->warning(
                '[AI] Stringifier {stringifier} failed for field type {type}: {message}',
                [
                    'stringifier' => $stringifier::class,
                    'type' => $type,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return '';
        }
    }

    /**
     * Whether a specific (non-fallback) stringifier is registered for the
     * given field type.
     */
    public function hasStringifier(string $fieldTypeIdentifier): bool
    {
        return isset($this->map[$fieldTypeIdentifier]);
    }
}
