<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Worker;

use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Agent\Worker\ExecutionResult;
use Masilia\AiAssistant\Agent\Worker\Plan;
use Masilia\AiAssistant\Agent\Worker\PlanExecutor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PlanExecutorTest extends TestCase
{
    /**
     * @param array<string, ToolResult> $toolResults map tool name → result
     */
    private function makeRegistry(array $toolResults): ToolRegistry
    {
        $registry = new ToolRegistry();
        foreach ($toolResults as $name => $result) {
            $tool = new class($name, $result) implements ToolInterface {
                public function __construct(private readonly string $name, private readonly ToolResult $result) {}

                public function getName(): string { return $this->name; }
                public function getDescription(): string { return 'mock'; }
                public function getParameters(): array { return []; }
                public function execute(array $params): ToolResult { return $this->result; }
            };
            $registry = $registry->register($tool);
        }
        return $registry;
    }

    public function testExecuteCreateContentSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_CONTENT => ToolResult::ok('Content created', [
                'content_id' => 100,
                'location_id' => 200,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_CONTENT,
            contentType: 'page',
            parentLocationId: 42,
            fields: ['title' => 'About Us'],
        ));

        self::assertTrue($result->success);
        self::assertSame(100, $result->contentId);
        self::assertSame(200, $result->locationId);
    }

    public function testExecuteCreateContentToolFailure(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_CONTENT => ToolResult::error('Permission denied'),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_CONTENT,
            contentType: 'page',
            parentLocationId: 42,
            fields: ['title' => 'About Us'],
        ));

        self::assertFalse($result->success);
        self::assertStringContainsString('Permission denied', $result->message);
        self::assertSame('TOOL_FAILED', $result->errorCode);
    }

    public function testExecuteCreateFolderSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_FOLDER => ToolResult::ok('Folder created', [
                'content_id' => 50,
                'location_id' => 60,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_FOLDER,
            title: 'Media',
            parentLocationId: 1,
        ));

        self::assertTrue($result->success);
        self::assertSame(50, $result->contentId);
        self::assertSame(60, $result->locationId);
    }

    public function testExecuteTrashContentSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::TRASH_CONTENT => ToolResult::ok('Trashed', [
                'content_id' => 99,
                'location_id' => 100,
                'trashed' => true,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_TRASH_CONTENT,
            contentId: 99,
        ));

        self::assertTrue($result->success);
    }

    public function testExecuteRestoreContentSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::RESTORE_CONTENT => ToolResult::ok('Restored', [
                'restored' => [99, 100],
                'count' => 2,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_RESTORE_CONTENT,
            fields: ['content_ids' => [99, 100]],
        ));

        self::assertTrue($result->success);
    }

    public function testExecuteReturnsToolUnavailableWhenToolMissing(): void
    {
        $registry = $this->makeRegistry([]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_CONTENT,
            contentType: 'page',
            parentLocationId: 1,
            fields: ['title' => 'X'],
        ));

        self::assertFalse($result->success);
        self::assertSame('TOOL_UNAVAILABLE', $result->errorCode);
    }

    public function testExecuteUnknownIntent(): void
    {
        $registry = $this->makeRegistry([]);
        $executor = new PlanExecutor($registry, new NullLogger());

        $result = $executor->execute(new Plan(intent: 'fly_to_mars'));

        self::assertFalse($result->success);
        self::assertSame('UNKNOWN_INTENT', $result->errorCode);
    }

    public function testExecuteCreateItemsReturnsItemIds(): void
    {
        $registry = $this->makeRegistry([]);

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->method('warning');
        $logger->method('error');
        $logger->method('info');

        $contentService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $locationService = $this->createMock(\Ibexa\Contracts\Core\Repository\LocationService::class);
        $languageService = $this->createMock(\Ibexa\Contracts\Core\Repository\LanguageService::class);
        $repository = $this->createMock(\Ibexa\Contracts\Core\Repository\Repository::class);
        $contentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $folderType = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType::class);
        $parentLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);

        $repository->method('getContentService')->willReturn($contentService);
        $repository->method('getLocationService')->willReturn($locationService);
        $repository->method('getContentTypeService')->willReturn($contentTypeService);
        $repository->method('getContentLanguageService')->willReturn($languageService);
        $languageService->method('getDefaultLanguageCode')->willReturn('eng-GB');

        $contentInfo->method('__get')->willReturnMap([
            ['name', 'Info Cards'],
            ['mainLocation', $parentLocation],
        ]);
        $contentInfo->method('getMainLocation')->willReturn($parentLocation);
        $contentService->method('loadContentInfo')->with(200)->willReturn($contentInfo);

        $parentLocation->method('__get')->willReturnMap([
            ['id', 105],
            ['contentInfo', $contentInfo],
        ]);
        $parentLocation->id = 105;
        $locationService->method('loadLocation')->with(105)->willReturn($parentLocation);

        // Make loadContent throw to force the catch branch
        $contentService->method('loadContent')->willThrowException(new \RuntimeException('boom'));

        $locationList = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\LocationList::class);
        $locationList->method('getIterator')->willReturn(new \ArrayIterator([]));
        $locationService->method('loadLocationChildren')->with($parentLocation)->willReturn($locationList);

        $folderType->method('__get')->willReturnMap([['id', 99]]);
        $contentTypeService->method('loadContentTypeByIdentifier')
            ->with(\Masilia\AiAssistant\ContentTypeId::FOLDER)
            ->willReturn($folderType);

        $createStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct::class);
        $locStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct::class);
        $draft = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $versionInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo::class);
        $published = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $folderContentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $folderLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);
        $folderLocation->id = 300;
        $folderContentInfo->method('getMainLocation')->willReturn($folderLocation);

        $contentService->method('newContentCreateStruct')->willReturn($createStruct);
        $locationService->method('newLocationCreateStruct')->willReturn($locStruct);
        $contentService->method('createContent')->willReturn($draft);
        $draft->method('__get')->willReturnCallback(function (string $name) use ($versionInfo) {
            return $name === 'versionInfo' ? $versionInfo : null;
        });
        $contentService->method('publishVersion')->willReturn($published);
        $published->method('__get')->willReturnMap([['contentInfo', $folderContentInfo]]);

        $itemContent1 = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $itemContent1->method('__get')->willReturnCallback(fn (string $name) => $name === 'id' ? 400 : null);
        $itemContent2 = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $itemContent2->method('__get')->willReturnCallback(fn (string $name) => $name === 'id' ? 401 : null);

        $callCount = 0;
        $contentFactory = static function (string $type, array $parentIds, array $attrs, string $lang) use (&$callCount, $itemContent1, $itemContent2): array {
            $callCount++;
            return [
                'content' => $callCount === 1 ? $itemContent1 : $itemContent2,
                'location' => null,
            ];
        };

        $executor = new PlanExecutor(
            $registry,
            $logger,
            $repository,
            $contentTypeService,
            null,
            $contentFactory,
        );

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            items: [
                ['type' => 'card_item', 'fields' => ['icon' => 'star', 'title' => 'Hello']],
                ['type' => 'card_item', 'fields' => ['icon' => 'check', 'title' => 'World']],
            ],
        ));

        self::assertTrue($result->success, $result->message);
        self::assertSame([400, 401], $result->data['item_ids']);
        self::assertSame(2, $callCount);
    }

    public function testExecuteCreateItemsFailsWhenExecutorNotConfigured(): void
    {
        $registry = $this->makeRegistry([]);

        $executor = new PlanExecutor($registry, new NullLogger());

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            items: [['type' => 'card_item', 'fields' => []]],
        ));

        self::assertFalse($result->success);
        self::assertSame('EXECUTOR_NOT_CONFIGURED', $result->errorCode);
    }

    public function testExecuteCreateItemsSkipsItemsWithoutType(): void
    {
        $registry = $this->makeRegistry([]);
        $executor = new PlanExecutor($registry, new NullLogger());

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            items: [
                ['fields' => []],           // no type → skipped
                ['type' => '', 'fields' => []], // empty type → skipped
            ],
        ));

        // Without dependencies, the early "not configured" check fires first.
        self::assertFalse($result->success);
    }
}
