<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent;

use Masilia\AiAssistant\Agent\AgentPlan;
use Masilia\AiAssistant\Agent\AgentResponse;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use PHPUnit\Framework\TestCase;

final class AgentResponseTest extends TestCase
{
    public function testWithErrorCreatesFailureResponse(): void
    {
        $response = AgentResponse::error('Something went wrong');

        self::assertFalse($response->success);
        self::assertSame('Something went wrong', $response->message);
        self::assertSame([], $response->results);
        self::assertNull($response->plan);
    }

    public function testWithResultsCreatesSuccessResponseWhenAllSucceed(): void
    {
        $results = [
            ToolResult::ok('First'),
            ToolResult::ok('Second'),
        ];

        $response = AgentResponse::withResults($results, 'All done');

        self::assertTrue($response->success);
        self::assertSame('All done', $response->message);
        self::assertCount(2, $response->results);
    }

    public function testWithResultsCreatesFailureResponseWhenAnyFails(): void
    {
        $results = [
            ToolResult::ok('First'),
            ToolResult::error('Second failed'),
        ];

        $response = AgentResponse::withResults($results);

        self::assertFalse($response->success);
    }

    public function testWithPlanCreatesResponseWithPlan(): void
    {
        $plan = new AgentPlan(
            steps: [['tool' => 'test', 'params' => []]],
            description: 'Test plan',
            requiresApproval: true,
        );

        $response = AgentResponse::withPlan($plan, 'Here is your plan');

        self::assertTrue($response->success);
        self::assertSame('Here is your plan', $response->message);
        self::assertSame($plan, $response->plan);
        self::assertSame([], $response->results);
    }

    public function testWithPlanUsesDefaultMessage(): void
    {
        $plan = new AgentPlan(
            steps: [],
            description: '',
            requiresApproval: false,
        );

        $response = AgentResponse::withPlan($plan);

        self::assertSame('Here is the plan for your request:', $response->message);
    }

    public function testToArrayReturnsCorrectShape(): void
    {
        $response = AgentResponse::error('Error');

        $array = $response->toArray();

        self::assertArrayHasKey('message', $array);
        self::assertArrayHasKey('results', $array);
        self::assertArrayHasKey('plan', $array);
        self::assertArrayHasKey('success', $array);
        self::assertFalse($array['success']);
        self::assertNull($array['plan']);
    }

    public function testToArrayIncludesPlanWhenPresent(): void
    {
        $plan = new AgentPlan(
            steps: [['tool' => 'create_content', 'params' => ['content_type' => 'article']]],
            description: 'Create article',
            requiresApproval: true,
        );

        $response = AgentResponse::withPlan($plan, 'Plan ready');

        $array = $response->toArray();

        self::assertNotNull($array['plan']);
        self::assertSame('Create article', $array['plan']['description']);
        self::assertTrue($array['plan']['requiresApproval']);
        self::assertCount(1, $array['plan']['steps']);
    }

    public function testToArrayIncludesResultsWhenPresent(): void
    {
        $results = [
            ToolResult::ok('Created', ['content_id' => 42]),
        ];

        $response = AgentResponse::withResults($results, 'Content created');

        $array = $response->toArray();

        self::assertCount(1, $array['results']);
        self::assertTrue($array['results'][0]['success']);
        self::assertSame(42, $array['results'][0]['data']['content_id']);
    }
}
