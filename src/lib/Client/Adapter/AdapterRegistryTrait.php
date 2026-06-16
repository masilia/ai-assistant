<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * Shared logic for adapter registries.
 *
 * Both ProviderAdapterRegistry and ImageAdapterRegistry follow the
 * same pattern: accept iterable adapters, build a provider-id → adapter
 * map, and expose getForProvider(). This trait eliminates the structural
 * duplication.
 */
trait AdapterRegistryTrait
{
    /** @var array<string, object> */
    private array $map = [];

    /**
     * Build the provider-id → adapter map from an iterable of adapters.
     *
     * @param iterable<object>  $adapters
     * @param callable(object, string): bool $supports  Predicate: does adapter support provider $id?
     */
    private function buildMap(iterable $adapters, callable $supports): void
    {
        $list = $adapters instanceof \Traversable
            ? iterator_to_array($adapters)
            : $adapters;

        foreach ($list as $adapter) {
            foreach (ProviderId::ALL as $id) {
                if ($supports($adapter, $id)) {
                    $this->map[$id] = $adapter;
                }
            }
        }
    }

    /**
     * @return object
     */
    private function getFromMap(string $providerIdentifier, string $adapterLabel): object
    {
        return $this->map[$providerIdentifier]
            ?? throw new \RuntimeException(
                sprintf(
                    'No %s adapter found for identifier "%s". Registered adapters: %s',
                    $adapterLabel,
                    $providerIdentifier,
                    implode(', ', array_keys($this->map))
                )
            );
    }
}
