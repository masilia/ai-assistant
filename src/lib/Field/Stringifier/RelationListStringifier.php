<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;
use Throwable;

final readonly class RelationListStringifier implements FieldValueStringifierInterface
{
    /** Maximum related items to resolve (avoids huge payloads on large relations). */
    private const MAX_ITEMS = 10;

    public function __construct(
        private readonly ContentService $contentService,
    )
    {
    }

    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZOBJECTRELATIONLIST];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $allIds = $field->value->destinationContentIds ?? [];

        if ($allIds === []) {
            return '';
        }

        $ids = array_slice($allIds, 0, self::MAX_ITEMS);

        try {
            $contentInfoList = $this->contentService->loadContentInfoList($ids);
        } catch (Throwable) {
            return '';
        }

        // Preserve the original order from the relation list.
        $names = [];
        foreach ($contentInfoList as $info) {
            $names[] = $info->getName();
        }

        return implode(', ', $names);
    }
}
