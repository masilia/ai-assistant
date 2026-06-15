<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\FieldTypeRichText\RichText\InputHandlerInterface;
use Ibexa\FieldTypeRichText\FieldType\RichText\Value;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Converts LLM output into a valid ezrichtext Value for Ibexa.
 *
 * Wraps HTML in the XHTML5 edit namespace, runs Ibexa's InputHandler
 * (normalize → parse → validate → XSLT convert), and returns a Value
 * object with the resulting DocBook DOMDocument.
 *
 * Returning a Value object (instead of a string) prevents double-processing
 * by acceptValue(), which would otherwise re-parse the XML string.
 */
readonly class RichTextTransformer implements FieldValueTransformerInterface
{
    private const XHTML5_EDIT_NS = 'http://ibexa.co/namespaces/ezpublish5/xhtml5/edit';

    public function __construct(
        private InputHandlerInterface $inputHandler,
    ) {
    }

    public function getFieldTypeIdentifier(): string
    {
        return 'ezrichtext';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        // If already a Value object, pass through
        if ($value instanceof Value) {
            return $value;
        }

        // If already DocBook XML (has DocBook namespace), wrap and let InputHandler pass it through
        $trimmed = ltrim($value);
        if (str_starts_with($trimmed, '<')
            && (str_contains($trimmed, 'xmlns="http://docbook.org/ns/docbook"')
                || str_contains($trimmed, 'xmlns="http://ez.no/namespaces/ezpublish/docbook/"'))) {
            $docbookDom = $this->inputHandler->fromString($value);

            return new Value($docbookDom);
        }

        // HTML or plain text — wrap in XHTML5 edit namespace, let InputHandler convert to DocBook
        if (!str_starts_with($trimmed, '<')) {
            // Plain text — wrap in <p> tags
            $value = sprintf('<p>%s</p>', htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        $wrapped = sprintf(
            '<section xmlns="%s">%s</section>',
            self::XHTML5_EDIT_NS,
            $value,
        );

        $docbookDom = $this->inputHandler->fromString($wrapped);

        return new Value($docbookDom);
    }
}
