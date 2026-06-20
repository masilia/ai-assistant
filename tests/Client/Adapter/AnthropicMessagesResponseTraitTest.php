<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Client\Adapter;

use Masilia\AiAssistant\Client\Adapter\AnthropicMessagesResponseTrait;
use Masilia\AiAssistant\Client\ProviderLimits;
use PHPUnit\Framework\TestCase;

/**
 * Tests the OpenAI → Anthropic message format conversion that
 * buildAnthropicToolRequestBody performs internally.
 */
final class AnthropicMessagesResponseTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        // Anonymous class using the trait + a getLimits() stub.
        $this->subject = new class {
            use AnthropicMessagesResponseTrait;

            public function getLimits(): ProviderLimits
            {
                return ProviderLimits::anthropicMessages('MiniMax-M2.5');
            }

            public function convert(array $messages, array $tools = []): array
            {
                return $this->buildAnthropicToolRequestBody(
                    'MiniMax-M2.5',
                    0.7,
                    4096,
                    $messages,
                    $tools,
                );
            }
        };
    }

    public function testSystemMessageIsExtractedToTopLevel(): void
    {
        $body = $this->subject->convert([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'hi'],
        ]);

        self::assertSame('You are helpful.', $body['system']);
        self::assertCount(1, $body['messages']);
        self::assertSame('user', $body['messages'][0]['role']);
        self::assertSame('hi', $body['messages'][0]['content']);
    }

    public function testAssistantToolCallsBecomeContentBlocks(): void
    {
        $body = $this->subject->convert([
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'do it'],
            [
                'role' => 'assistant',
                'content' => 'Calling tool',
                'tool_calls' => [
                    ['id' => 'toolu_abc', 'name' => 'explore_site', 'arguments' => ['siteaccess' => 'fossilexit']],
                ],
            ],
        ]);

        $assistant = $body['messages'][1];
        self::assertSame('assistant', $assistant['role']);
        self::assertIsArray($assistant['content']);

        $types = array_map(static fn($b) => $b['type'], $assistant['content']);
        self::assertSame(['text', 'tool_use'], $types);

        self::assertSame('Calling tool', $assistant['content'][0]['text']);
        $toolUse = $assistant['content'][1];
        self::assertSame('tool_use', $toolUse['type']);
        self::assertSame('toolu_abc', $toolUse['id']);
        self::assertSame('explore_site', $toolUse['name']);
        self::assertSame(['siteaccess' => 'fossilexit'], $toolUse['input']);
    }

    public function testAssistantToolCallsNestedFormatBecomeContentBlocks(): void
    {
        $body = $this->subject->convert([
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'do it'],
            [
                'role' => 'assistant',
                'content' => 'Calling tool',
                'tool_calls' => [
                    [
                        'id' => 'toolu_abc',
                        'type' => 'function',
                        'function' => [
                            'name' => 'explore_site',
                            'arguments' => '{"siteaccess":"fossilexit"}',
                        ],
                    ],
                ],
            ],
        ]);

        $assistant = $body['messages'][1];
        self::assertSame('assistant', $assistant['role']);

        $toolUse = $assistant['content'][1];
        self::assertSame('tool_use', $toolUse['type']);
        self::assertSame('toolu_abc', $toolUse['id']);
        self::assertSame('explore_site', $toolUse['name']);
        self::assertSame(['siteaccess' => 'fossilexit'], $toolUse['input']);
    }

    public function testToolMessageBecomesUserMessageWithToolResultBlock(): void
    {
        $body = $this->subject->convert([
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'do it'],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [
                    ['id' => 'toolu_abc', 'name' => 'explore_site', 'arguments' => []],
                ],
            ],
            [
                'role' => 'tool',
                'tool_call_id' => 'toolu_abc',
                'content' => '{"success":true,"message":"done","data":{}}',
            ],
        ]);

        self::assertCount(3, $body['messages']);

        $toolResultMsg = $body['messages'][2];
        self::assertSame('user', $toolResultMsg['role']);
        self::assertIsArray($toolResultMsg['content']);
        self::assertCount(1, $toolResultMsg['content']);

        $block = $toolResultMsg['content'][0];
        self::assertSame('tool_result', $block['type']);
        self::assertSame('toolu_abc', $block['tool_use_id']);
        self::assertSame('{"success":true,"message":"done","data":{}}', $block['content']);
    }

    public function testFullConversationRoundTrip(): void
    {
        // Realistic conversation: system → user → assistant tool call → tool result
        $body = $this->subject->convert([
            ['role' => 'system', 'content' => 'You are a CMS assistant.'],
            ['role' => 'user', 'content' => 'design page about fossil exit'],
            [
                'role' => 'assistant',
                'content' => "I'll explore first.",
                'tool_calls' => [
                    ['id' => 'call_1', 'name' => 'explore_site', 'arguments' => ['siteaccess' => 'fossilexit']],
                ],
            ],
            [
                'role' => 'tool',
                'tool_call_id' => 'call_1',
                'content' => '{"success":true,"data":{"matchedSiteaccess":"fossilexit"}}',
            ],
        ]);

        self::assertSame('You are a CMS assistant.', $body['system']);
        self::assertCount(3, $body['messages']);

        // Message 0: user
        self::assertSame('user', $body['messages'][0]['role']);
        self::assertSame('design page about fossil exit', $body['messages'][0]['content']);

        // Message 1: assistant → tool_use block
        self::assertSame('assistant', $body['messages'][1]['role']);
        self::assertCount(2, $body['messages'][1]['content']);
        self::assertSame('text', $body['messages'][1]['content'][0]['type']);
        self::assertSame("I'll explore first.", $body['messages'][1]['content'][0]['text']);
        self::assertSame('tool_use', $body['messages'][1]['content'][1]['type']);
        self::assertSame('call_1', $body['messages'][1]['content'][1]['id']);

        // Message 2: tool → user with tool_result block
        self::assertSame('user', $body['messages'][2]['role']);
        self::assertSame('tool_result', $body['messages'][2]['content'][0]['type']);
        self::assertSame('call_1', $body['messages'][2]['content'][0]['tool_use_id']);
    }

    public function testAssistantWithoutToolCallsPassesThroughPlain(): void
    {
        $body = $this->subject->convert([
            ['role' => 'user', 'content' => 'hello'],
            ['role' => 'assistant', 'content' => 'hi there'],
        ]);

        // Plain assistant with no tool_calls should stay as plain user/assistant
        self::assertSame('hi there', $body['messages'][1]['content']);
    }

    public function testToolsArrayIsPreserved(): void
    {
        $body = $this->subject->convert(
            [['role' => 'user', 'content' => 'go']],
            [
                [
                    'name' => 'explore_site',
                    'description' => 'Explore the site',
                    'parameters' => ['type' => 'object', 'properties' => ['siteaccess' => ['type' => 'string']]],
                ],
            ],
        );

        self::assertArrayHasKey('tools', $body);
        self::assertCount(1, $body['tools']);
        self::assertSame('explore_site', $body['tools'][0]['name']);
        self::assertArrayHasKey('input_schema', $body['tools'][0]);
    }
}
