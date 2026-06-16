<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

class ProviderAdapterRegistry
{
    use AdapterRegistryTrait;

    /**
     * @param iterable<ProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->buildMap($adapters, static fn (ProviderAdapterInterface $adapter, string $id): bool => $adapter->supports($id));
    }

    public function getForProvider(string $providerIdentifier): ProviderAdapterInterface
    {
        /** @var ProviderAdapterInterface */
        return $this->getFromMap($providerIdentifier, 'AI provider');
    }
}
