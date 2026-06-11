<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ImageProviderAdapterInterface;

/**
 * Registry for image generation adapters. Resolves a provider identifier
 * to the corresponding {@see ImageProviderAdapterInterface} implementation.
 */
class ImageAdapterRegistry
{
    /** @var array<string, ImageProviderAdapterInterface> */
    private array $map;

    /**
     * @param iterable<ImageProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $list = $adapters instanceof \Traversable
            ? iterator_to_array($adapters)
            : $adapters;

        $this->map = [];
        foreach ($list as $adapter) {
            foreach (ProviderId::ALL as $id) {
                if ($adapter->supportsImageGeneration($id)) {
                    $this->map[$id] = $adapter;
                }
            }
        }
    }

    public function getForProvider(string $providerIdentifier): ImageProviderAdapterInterface
    {
        return $this->map[$providerIdentifier]
            ?? throw new \RuntimeException(
                sprintf(
                    'No image generation adapter found for identifier "%s". Registered adapters: %s',
                    $providerIdentifier,
                    implode(', ', array_keys($this->map))
                )
            );
    }
}
