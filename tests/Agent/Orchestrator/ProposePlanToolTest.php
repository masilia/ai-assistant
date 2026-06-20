<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Orchestrator;

use Masilia\AiAssistant\Agent\Orchestrator\ProposePlanTool;
use Masilia\AiAssistant\Agent\Orchestrator\WorkerContext;
use Masilia\AiAssistant\Agent\Wizard\WizardState;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Agent\Worker\PlanBuilder;
use Masilia\AiAssistant\Agent\Worker\PlanExecutor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProposePlanToolTest extends TestCase
{
    public static int $executions = 0;

    private function makeTool(): ProposePlanTool
    {
        $stubTool = new class implements ToolInterface {
            public function getName(): string
            {
                return ToolName::CREATE_CONTENT;
            }

            public function getDescription(): string
            {
                return 'stub';
            }

            public function getParameters(): array
            {
                return [];
            }

            public function execute(array $params): ToolResult
            {
                ProposePlanToolTest::$executions++;

                return ToolResult::ok('Stub executed', [
                    'content_id' => 100,
                    'location_id' => 200,
                ]);
            }
        };

        $registry = (new ToolRegistry())->register($stubTool);

        return new ProposePlanTool(
            new PlanBuilder(),
            new PlanExecutor($registry, new NullLogger()),
            new NullLogger(),
        );
    }

    protected function setUp(): void
    {
        self::$executions = 0;
    }

    public function testFirstCallSavesPlanAndAwaitsApproval(): void
    {
        $tool = $this->makeTool();
        $state = new WizardState();

        $response = $tool->execute([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
        ], new WorkerContext(1, $state));

        self::assertTrue($response->success);
        self::assertNotNull($response->proposedPlanDelta);
        self::assertSame('create_content', $response->proposedPlanDelta['intent']);
        self::assertSame('page', $response->proposedPlanDelta['content_type']);
        self::assertFalse($response->isTerminal);
        self::assertStringContainsString('Shall I proceed', $response->message);
        self::assertSame(0, self::$executions, 'PlanExecutor must NOT run on first propose');
    }

    public function testExactSameArgsReInvocationExecutesPlan(): void
    {
        $tool = $this->makeTool();
        $state = (new WizardState())->withProposedPlan([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'blocks' => [],
            'content_id' => null,
            'description' => null,
            'siteaccess' => null,
            'title' => null,
        ]);

        $response = $tool->execute([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
        ], new WorkerContext(1, $state));

        self::assertTrue($response->success);
        self::assertNull($response->proposedPlanDelta, 'Approval must NOT save a new plan delta');
        self::assertSame(100, $response->data['content_id']);
        self::assertSame(1, self::$executions);
    }

    public function testFieldVariationStillCountsAsApproval(): void
    {
        $tool = $this->makeTool();
        $state = (new WizardState())->withProposedPlan([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'blocks' => [],
            'content_id' => null,
            'description' => null,
            'siteaccess' => null,
            'title' => null,
        ]);

        // Same content type, slightly different field values → still approval
        $response = $tool->execute([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us', 'subtitle' => 'new'],
        ], new WorkerContext(1, $state));

        self::assertNull($response->proposedPlanDelta, 'Field variation must count as approval, not modification');
        self::assertSame(1, self::$executions);
    }

    public function testContentTypeChangeIsTreatedAsModification(): void
    {
        $tool = $this->makeTool();
        $state = (new WizardState())->withProposedPlan([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'blocks' => [],
            'content_id' => null,
            'description' => null,
            'siteaccess' => null,
            'title' => null,
        ]);

        $response = $tool->execute([
            'intent' => 'create_content',
            'content_type' => 'article',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
        ], new WorkerContext(1, $state));

        self::assertNotNull($response->proposedPlanDelta, 'Changing content_type is a modification');
        self::assertSame(0, self::$executions);
    }

    public function testParentLocationChangeIsTreatedAsModification(): void
    {
        $tool = $this->makeTool();
        $state = (new WizardState())->withProposedPlan([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'blocks' => [],
            'content_id' => null,
            'description' => null,
            'siteaccess' => null,
            'title' => null,
        ]);

        $response = $tool->execute([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 99,  // changed
            'fields' => ['title' => 'About Us'],
        ], new WorkerContext(1, $state));

        self::assertNotNull($response->proposedPlanDelta, 'parent_location_id change is a modification');
        self::assertSame(0, self::$executions);
    }

    public function testDescriptionVariationStillCountsAsApproval(): void
    {
        $tool = $this->makeTool();
        $state = (new WizardState())->withProposedPlan([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'blocks' => [],
            'content_id' => null,
            'description' => 'first description',
            'siteaccess' => null,
            'title' => null,
        ]);

        $response = $tool->execute([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'description' => 'totally different description text',
        ], new WorkerContext(1, $state));

        self::assertNull($response->proposedPlanDelta, 'description variations must not trigger modification');
        self::assertSame(1, self::$executions);
    }

    public function testInvalidPlanReturnsErrorResponse(): void
    {
        $tool = $this->makeTool();
        $state = new WizardState();

        $response = $tool->execute([
            'intent' => 'fly_to_mars',
        ], new WorkerContext(1, $state));

        self::assertFalse($response->success);
        self::assertStringContainsString('Unknown intent', $response->message);
        self::assertNull($response->proposedPlanDelta);
        self::assertSame(0, self::$executions);
    }
}
