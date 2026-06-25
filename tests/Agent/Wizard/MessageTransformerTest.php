<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Wizard;

use Masilia\AiAssistant\Agent\Wizard\MessageTransformer;
use PHPUnit\Framework\TestCase;

final class MessageTransformerTest extends TestCase
{
    public function testEmptyMessages(): void
    {
        self::assertSame([], MessageTransformer::toFrontend([]));
    }

    public function testSystemMessagesAreSkipped(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'system prompt'],
            ['role' => 'user', 'content' => 'hello'],
        ];

        $result = MessageTransformer::toFrontend($messages);

        self::assertCount(1, $result);
        self::assertSame('user', $result[0]['role']);
        self::assertSame('hello', $result[0]['content']);
    }

    public function testUserMessagesBecomeUserEntries(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'hello'],
            ['role' => 'user', 'content' => 'world'],
        ];

        $result = MessageTransformer::toFrontend($messages);

        self::assertCount(2, $result);
        self::assertSame('user', $result[0]['role']);
        self::assertSame('hello', $result[0]['content']);
        self::assertSame('user', $result[1]['role']);
        self::assertSame('world', $result[1]['content']);
    }

    public function testAssistantTextBecomesAgentEntry(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'assistant', 'content' => 'hello there'],
        ];

        $result = MessageTransformer::toFrontend($messages);

        self::assertCount(2, $result);
        self::assertSame('agent', $result[1]['role']);
        self::assertSame('hello there', $result[1]['content']);
    }

    public function testAssistantWithToolCallsDoesNotCreateEntry(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'create a page'],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [
                ['id' => 'call_1', 'name' => 'explore_site', 'arguments' => '{}'],
            ]],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"siteaccesses": ["fossilexit"]}'],
        ];

        $result = MessageTransformer::toFrontend($messages);

        // user + agent (standalone tool result)
        self::assertCount(2, $result);
        self::assertSame('user', $result[0]['role']);
        self::assertSame('agent', $result[1]['role']);
        self::assertNotEmpty($result[1]['toolOutputs']);
        self::assertSame('explore_site', $result[1]['toolOutputs'][0]['tool']);
    }

    public function testToolResultAppendsToLastAgentMessage(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'assistant', 'content' => 'let me check', 'tool_calls' => [
                ['id' => 'call_1', 'name' => 'explore_site', 'arguments' => '{}'],
            ]],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"matched": true}'],
            ['role' => 'assistant', 'content' => 'I found the site.'],
        ];

        $result = MessageTransformer::toFrontend($messages);

        // user, agent (standalone tool), agent (text)
        self::assertCount(3, $result);
        self::assertSame('agent', $result[1]['role']);
        self::assertSame('', $result[1]['content']);
        self::assertSame('agent', $result[2]['role']);
        self::assertSame('I found the site.', $result[2]['content']);
    }
}
