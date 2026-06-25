<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Worker;

/**
 * Result of PlanExecutor — structured outcome of executing a Plan.
 */
final readonly class ExecutionResult
{
    /**
     * @param array<string, mixed> $data Arbitrary data returned by the executor
     *                                     (e.g. ['item_ids' => [200, 201]] for create_items).
     */
    public function __construct(
        public bool   $success,
        public string $message,
        public ?int   $contentId = null,
        public ?int   $locationId = null,
        public ?string $errorCode = null,
        public array  $data = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(
        string $message,
        ?int $contentId = null,
        ?int $locationId = null,
        array $data = [],
    ): self {
        return new self(
            success: true,
            message: $message,
            contentId: $contentId,
            locationId: $locationId,
            data: $data,
        );
    }

    public static function fail(string $message, ?string $errorCode = null): self
    {
        return new self(success: false, message: $message, errorCode: $errorCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'content_id' => $this->contentId,
            'location_id' => $this->locationId,
            'error_code' => $this->errorCode,
            'data' => $this->data,
        ];
    }
}
