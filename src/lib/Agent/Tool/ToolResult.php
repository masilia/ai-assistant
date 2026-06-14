<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

readonly class ToolResult
{
    public function __construct(
        public bool   $success,
        public string $message = '',
        public array  $data = [],
    ) {
    }

    public static function ok(string $message = '', array $data = []): self
    {
        return new self(success: true, message: $message, data: $data);
    }

    public static function error(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * @return array{success: bool, message: string, data: array}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
