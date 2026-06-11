<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

readonly class AiError
{
    public function __construct(
        public string $code,
        public string $message,
    )
    {
    }

    public static function accessDenied(): self
    {
        return new self('ACCESS_DENIED', 'Access Denied');
    }

    public static function validationError(string $message): self
    {
        return new self('VALIDATION_ERROR', $message);
    }

    public static function unsupportedFieldType(string $fieldType): self
    {
        return new self('UNSUPPORTED_FIELD_TYPE', sprintf('Unsupported field type: %s', $fieldType));
    }

    public static function serviceUnavailable(string $message): self
    {
        return new self('SERVICE_UNAVAILABLE', $message);
    }

    public static function internalError(string $message): self
    {
        return new self('INTERNAL_ERROR', $message);
    }

    public function toArray(): array
    {
        return [
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
        ];
    }
}
