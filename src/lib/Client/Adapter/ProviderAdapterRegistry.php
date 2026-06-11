<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class ProviderAdapterRegistry
{
    /** @var array<string, ProviderAdapterInterface> */
    private array $map;

    /**
     * @param iterable<ProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $list = $adapters instanceof \Traversable
            ? iterator_to_array($adapters)
            : $adapters;

        // Build an identifier → adapter map so getForProvider() is O(1).
        $this->map = [];
        foreach ($list as $adapter) {
            foreach (ProviderId::ALL as $id) {
                if ($adapter->supports($id)) {
                    $this->map[$id] = $adapter;
                }
            }
        }
    }

    public function getForProvider(string $providerIdentifier): ProviderAdapterInterface
    {
        return $this->map[$providerIdentifier]
            ?? throw new \RuntimeException(
                sprintf(
                    'No AI provider adapter found for identifier "%s". Registered adapters: %s',
                    $providerIdentifier,
                    implode(', ', array_keys($this->map))
                )
            );
    }
}
