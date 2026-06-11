<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Masilia\AiAssistant\DTO\AiError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared JSON-request decoding for the AI assistant controllers.
 *
 * All AI endpoints accept JSON bodies, run them through the same
 * `json_decode(..., JSON_THROW_ON_ERROR)` call, and translate the
 * `\JsonException` to an `AiError::validationError('Invalid JSON payload')`
 * response. This trait centralises that flow so the three controllers
 * (AiSuggestController, AiProviderApiController, AiModelApiController)
 * stay in lockstep.
 */
trait JsonRequestDecoder
{
    /**
     * Decode the JSON body of a request into an associative array.
     *
     * Returns `null` when the payload is not valid JSON. Callers should
     * translate that into a 400 response via {@see jsonErrorResponse()}
     * or {@see AiError::validationError()}.
     *
     * The `?? []` fallback is intentionally absent: `JSON_THROW_ON_ERROR`
     * makes the function either throw or return a real value (an array
     * or a scalar), and a JSON literal `null` is not a valid AI request
     * payload.
     *
     * @return array<string, mixed>
     */
    private function decodeJsonRequest(Request $request): ?array
    {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function jsonErrorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse(AiError::validationError($message)->toArray(), $status);
    }
}
