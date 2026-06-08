<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * Opt-in connection-test support. Adapters that implement this interface
 * can be reached via the admin dashboard's
 * 'POST /admin/ai/settings/api/provider/{id}/test' endpoint.
 *
 * Adapters that don't (e.g. a custom OpenAI-compatible proxy that has
 * no default test model) can still implement just
 * {@see ProviderAdapterInterface}.
 */
interface TestableProviderAdapterInterface extends ProviderAdapterInterface
{
    public function buildTestRequestBody(string $modelIdentifier): array;

    public function getDefaultTestModel(): string;
}
