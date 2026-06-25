<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Wizard;

/**
 * Transform provider-native messages to frontend ChatMessage format.
 *
 * Provider format:
 *   {role: 'system', content: '...'}
 *   {role: 'user', content: '...'}
 *   {role: 'assistant', content: '...', tool_calls: [{id, name, arguments}]}
 *   {role: 'tool', tool_call_id: '...', content: '...'}
 *
 * Frontend format:
 *   {role: 'user'|'agent', content: '...', timestamp: '...', toolOutputs?: [...], options?: [...]}
 */
final class MessageTransformer
{
    /**
     * @param array $messages Provider-native messages
     * @return array Frontend ChatMessage array
     */
    public static function toFrontend(array $messages): array
    {
        $result = [];
        $pendingToolCalls = [];
        $timestamp = date('H:i');

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';

            if ($role === 'system') {
                continue;
            }

            if ($role === 'user') {
                $result[] = [
                    'role' => 'user',
                    'content' => $msg['content'] ?? '',
                    'timestamp' => $timestamp,
                ];
                continue;
            }

            if ($role === 'assistant') {
                $toolCalls = $msg['tool_calls'] ?? [];
                if ($toolCalls !== []) {
                    $pendingToolCalls = $toolCalls;
                }

                $content = $msg['content'] ?? '';
                if ($content !== '' && $toolCalls === []) {
                    $result[] = [
                        'role' => 'agent',
                        'content' => $content,
                        'timestamp' => $timestamp,
                    ];
                }
                continue;
            }

            if ($role === 'tool') {
                $toolCallId = $msg['tool_call_id'] ?? '';
                $toolName = '';
                foreach ($pendingToolCalls as $tc) {
                    if (($tc['id'] ?? '') === $toolCallId) {
                        $toolName = $tc['name'] ?? '';
                        break;
                    }
                }

                $toolOutput = [
                    'tool' => $toolName,
                    'output' => json_decode($msg['content'] ?? '{}', true) ?? [],
                ];

                // Find the last agent message and append tool output
                $lastIdx = array_key_last($result);
                if ($lastIdx !== null && ($result[$lastIdx]['role'] ?? '') === 'agent') {
                    $result[$lastIdx]['toolOutputs'][] = $toolOutput;
                } else {
                    // Standalone tool result — create an agent entry
                    $result[] = [
                        'role' => 'agent',
                        'content' => '',
                        'timestamp' => $timestamp,
                        'toolOutputs' => [$toolOutput],
                    ];
                }
            }
        }

        return $result;
    }
}
