<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

class ProviderAdapterRegistry
{
    /** @var ProviderAdapterInterface[] */
    private array $adapters;

    /**
     * @param iterable<ProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->adapters = $adapters instanceof \Traversable
            ? iterator_to_array($adapters)
            : $adapters;
    }

    public function getForProvider(string $providerIdentifier): ProviderAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($providerIdentifier)) {
                return $adapter;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'No AI provider adapter found for identifier "%s". Registered adapters: %s',
                $providerIdentifier,
                implode(', ', array_map(
                    fn($a) => $a::class,
                    $this->adapters
                ))
            )
        );
    }
}
