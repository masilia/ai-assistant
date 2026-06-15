<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent;

use Masilia\AiAssistant\Agent\AgentOrchestrator;
use Masilia\AiAssistant\Agent\AgentResponse;
use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Block\BlockDesigner;
use Masilia\AiAssistant\Agent\ContentResolver;
use Masilia\AiAssistant\Agent\DTO\PageDesign;
use Masilia\AiAssistant\Agent\IntentClassifier;
use Masilia\AiAssistant\Agent\SeoMetadataHandler;
use Masilia\AiAssistant\Agent\Tool\SiteaccessLocationResolver;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorTest extends TestCase
{
    private AgentOrchestrator $orchestrator;
    private IntentClassifier $classifier;
    private ToolRegistry $toolRegistry;
    private SiteaccessLocationResolver $locationResolver;
    private ContentResolver $contentResolver;
    private SeoMetadataHandler $seoHandler;
    private BlockDesigner $blockDesigner;

    protected function setUp(): void
    {
        $this->classifier = $this->createMock(IntentClassifier::class);

        $blockCatalog = $this->createMock(BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([
            'hero_banner' => ['identifier' => 'hero_banner', 'name' => 'Hero Banner', 'fields' => ['title' => 'ezstring']],
            'paragraph' => ['identifier' => 'paragraph', 'name' => 'Paragraph', 'fields' => ['rich_text' => 'ezrichtext']],
            'cta' => ['identifier' => 'cta', 'name' => 'CTA', 'fields' => ['title' => 'ezstring']],
        ]);
        $blockCatalog->method('getCapabilities')->willReturn([
            'hero' => ['hero_banner'],
            'text' => ['paragraph'],
            'cta' => ['cta'],
        ]);
        $blockCatalog->method('getBlockItemTypes')->willReturn([]);

        $this->toolRegistry = new ToolRegistry();

        $this->locationResolver = $this->createMock(SiteaccessLocationResolver::class);
        $this->contentResolver = $this->createMock(ContentResolver::class);
        $this->seoHandler = $this->createMock(SeoMetadataHandler::class);
        $this->blockDesigner = $this->createMock(BlockDesigner::class);
        // Default: pass blocks through unchanged so existing assertions stay valid
        $this->blockDesigner->method('designPageStructure')
            ->willReturnCallback(static fn(array $parsed) => PageDesign::fromArray($parsed));

        $this->orchestrator = new AgentOrchestrator(
            classifier: $this->classifier,
            blockCatalog: $blockCatalog,
            blockDesigner: $this->blockDesigner,
            toolRegistry: $this->toolRegistry,
            locationResolver: $this->locationResolver,
            contentResolver: $this->contentResolver,
            seoHandler: $this->seoHandler,
        );
    }

    public function testChatReturnsErrorWhenClassificationFails(): void
    {
        $this->classifier->method('classify')->willReturn(null);

        $response = $this->orchestrator->chat('hello');

        self::assertFalse($response->success);
        self::assertStringContainsString('could not understand', $response->message);
    }

    public function testChatReturnsPlanForCreatePageWithSiteaccess(): void
    {
        $this->classifier->method('classify')->willReturn([
            'intent' => 'create_page',
            'parameters' => [
                'title' => 'My Page',
                'siteaccess' => 'mattcch',
                'blocks' => [
                    ['type' => 'hero_banner', 'fields' => ['title' => 'Welcome']],
                    ['type' => 'cta', 'fields' => ['title' => 'Contact']],
                ],
            ],
        ]);

        $this->locationResolver->method('resolve')
            ->with('mattcch', null)
            ->willReturn(42);

        $response = $this->orchestrator->chat('create a page under mattcch site');

        self::assertTrue($response->success);
        self::assertNotNull($response->plan);
        self::assertStringContainsString('My Page', $response->message);
        self::assertStringContainsString('mattcch', $response->message);

        // Verify the plan uses create_page_structure with resolved location
        $step = $response->plan->steps[0];
        self::assertSame('create_page_structure', $step['tool']);
        self::assertSame(42, $step['params']['parent_location_id']);
        self::assertSame('My Page', $step['params']['title']);
        self::assertCount(2, $step['params']['blocks']);
    }

    public function testChatReturnsErrorWhenSiteaccessNotSpecified(): void
    {
        $this->classifier->method('classify')->willReturn([
            'intent' => 'create_page',
            'parameters' => [
                'title' => 'My Page',
                'blocks' => [],
            ],
        ]);

        // No siteaccess → try current siteaccess → fails
        $this->locationResolver->method('resolve')
            ->willReturn(null);

        $response = $this->orchestrator->chat('create a page');

        self::assertFalse($response->success);
        self::assertStringContainsString('specify a siteaccess', $response->message);
    }

    public function testChatFallsBackToCurrentSiteaccess(): void
    {
        $this->classifier->method('classify')->willReturn([
            'intent' => 'create_page',
            'parameters' => [
                'title' => 'My Page',
                'blocks' => [],
            ],
        ]);

        // Empty siteaccess falls back to current
        $this->locationResolver->method('resolve')
            ->with('', null)
            ->willReturn(99);

        $response = $this->orchestrator->chat('create a page');

        self::assertTrue($response->success);
        self::assertNotNull($response->plan);
        self::assertSame(99, $response->plan->steps[0]['params']['parent_location_id']);
    }

    public function testChatExecutesSearchContentTool(): void
    {
        $searchTool = $this->createMock(ToolInterface::class);
        $searchTool->method('getName')->willReturn('search_content');
        $searchTool->method('execute')->willReturn(
            ToolResult::ok('Found 2 results', ['count' => 2, 'results' => []])
        );

        $this->toolRegistry = $this->toolRegistry->register($searchTool);
        $this->orchestrator = new AgentOrchestrator(
            classifier: $this->classifier,
            blockCatalog: $this->createMock(BlockCatalog::class),
            blockDesigner: $this->blockDesigner,
            toolRegistry: $this->toolRegistry,
            locationResolver: $this->locationResolver,
            contentResolver: $this->contentResolver,
            seoHandler: $this->seoHandler,
        );

        $this->classifier->method('classify')->willReturn([
            'intent' => 'search_content',
            'parameters' => ['query' => 'climate'],
        ]);

        $response = $this->orchestrator->chat('find articles about climate');

        self::assertTrue($response->success);
        self::assertCount(1, $response->results);
    }

    public function testChatReturnsErrorForUnknownIntent(): void
    {
        $this->classifier->method('classify')->willReturn([
            'intent' => 'fly_to_moon',
            'parameters' => [],
        ]);

        $response = $this->orchestrator->chat('fly to the moon');

        self::assertFalse($response->success);
        self::assertStringContainsString('Unknown intent', $response->message);
    }

    public function testChatHandlesListBlocksIntent(): void
    {
        $this->classifier->method('classify')->willReturn([
            'intent' => 'list_blocks',
            'parameters' => [],
        ]);

        $response = $this->orchestrator->chat('what blocks are available');

        self::assertTrue($response->success);
        self::assertStringContainsString('hero_banner', $response->message);
    }

    public function testExecutePlanExecutesStepsSequentially(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('create_content');
        $tool->method('execute')->willReturn(ToolResult::ok('Created', ['content_id' => 1]));

        $this->toolRegistry = $this->toolRegistry->register($tool);

        $this->orchestrator = new AgentOrchestrator(
            classifier: $this->classifier,
            blockCatalog: $this->createMock(BlockCatalog::class),
            blockDesigner: $this->blockDesigner,
            toolRegistry: $this->toolRegistry,
            locationResolver: $this->locationResolver,
            contentResolver: $this->contentResolver,
            seoHandler: $this->seoHandler,
        );

        $plan = new \Masilia\AiAssistant\Agent\AgentPlan(
            steps: [
                ['tool' => 'create_content', 'params' => ['content_type' => 'article']],
                ['tool' => 'create_content', 'params' => ['content_type' => 'article']],
            ],
            description: 'Create two articles',
            requiresApproval: false,
        );

        $response = $this->orchestrator->executePlan($plan);

        self::assertTrue($response->success);
        self::assertCount(2, $response->results);
    }

    public function testExecutePlanStopsOnError(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('create_content');
        $tool->method('execute')->willReturn(ToolResult::error('Failed'));

        $this->toolRegistry = $this->toolRegistry->register($tool);

        $this->orchestrator = new AgentOrchestrator(
            classifier: $this->classifier,
            blockCatalog: $this->createMock(BlockCatalog::class),
            blockDesigner: $this->blockDesigner,
            toolRegistry: $this->toolRegistry,
            locationResolver: $this->locationResolver,
            contentResolver: $this->contentResolver,
            seoHandler: $this->seoHandler,
        );

        $plan = new \Masilia\AiAssistant\Agent\AgentPlan(
            steps: [
                ['tool' => 'create_content', 'params' => []],
                ['tool' => 'create_content', 'params' => []],
            ],
            description: 'Two steps',
            requiresApproval: false,
        );

        $response = $this->orchestrator->executePlan($plan);

        self::assertFalse($response->success);
        self::assertCount(1, $response->results);
    }

    public function testExecutePlanReturnsErrorForUnknownTool(): void
    {
        $plan = new \Masilia\AiAssistant\Agent\AgentPlan(
            steps: [
                ['tool' => 'nonexistent_tool', 'params' => []],
            ],
            description: 'Unknown tool',
            requiresApproval: false,
        );

        $response = $this->orchestrator->executePlan($plan);

        self::assertFalse($response->success);
        self::assertStringContainsString('Tool not found', $response->results[0]->message);
    }

    public function testChatHandlesUndoIntent(): void
    {
        $undoTool = $this->createMock(ToolInterface::class);
        $undoTool->method('getName')->willReturn('undo_last_operation');
        $undoTool->method('execute')->willReturn(ToolResult::ok('Undone'));

        $this->toolRegistry = $this->toolRegistry->register($undoTool);

        $this->orchestrator = new AgentOrchestrator(
            classifier: $this->classifier,
            blockCatalog: $this->createMock(BlockCatalog::class),
            blockDesigner: $this->blockDesigner,
            toolRegistry: $this->toolRegistry,
            locationResolver: $this->locationResolver,
            contentResolver: $this->contentResolver,
            seoHandler: $this->seoHandler,
        );

        $this->classifier->method('classify')->willReturn([
            'intent' => 'undo',
            'parameters' => [],
        ]);

        $response = $this->orchestrator->chat('undo that');

        self::assertTrue($response->success);
        self::assertCount(1, $response->results);
    }

    public function testChatReturnsErrorWhenSiteaccessResolutionFails(): void
    {
        $this->classifier->method('classify')->willReturn([
            'intent' => 'create_page',
            'parameters' => [
                'title' => 'My Page',
                'siteaccess' => 'nonexistent',
                'blocks' => [],
            ],
        ]);

        $this->locationResolver->method('resolve')
            ->with('nonexistent', null)
            ->willReturn(null);

        $response = $this->orchestrator->chat('create a page under nonexistent');

        self::assertFalse($response->success);
        self::assertStringContainsString('Could not resolve', $response->message);
        self::assertStringContainsString('nonexistent', $response->message);
    }
}
