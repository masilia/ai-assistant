<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;
use Throwable;

final class RelationStringifier implements FieldValueStringifierInterface
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {
    }

    public static function getSupportedFieldTypes(): array
    {
        return ['ezobjectrelation'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $relId = $field->value->destinationContentId ?? null;

        if (!$relId) {
            return '';
        }

        try {
            return $this->contentService->loadContentInfo((int) $relId)->getName();
        } catch (Throwable) {
            return '';
        }
    }
}
