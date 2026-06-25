<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;

/**
 * Creates and publishes content items.
 *
 * Abstraction over ContentCreator so PlanExecutor can depend on an interface
 * instead of a concrete class, improving testability.
 */
interface ContentFactoryInterface
{
    /**
     * @param array<int> $parentLocationIds
     * @param array<string, mixed> $attributes
     * @return array{content: Content, location: ?Location}
     */
    public function createAndPublish(
        string $contentTypeIdentifier,
        array $parentLocationIds,
        array $attributes,
        string $languageCode,
    ): array;
}
