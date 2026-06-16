<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\AdapterRegistryTrait;
use Masilia\AiAssistant\Client\Adapter\ImageProviderAdapterInterface;

/**
 * Registry for image generation adapters. Resolves a provider identifier
 * to the corresponding {@see ImageProviderAdapterInterface} implementation.
 */
class ImageAdapterRegistry
{
    use AdapterRegistryTrait;

    /**
     * @param iterable<ImageProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->buildMap($adapters, static fn (ImageProviderAdapterInterface $adapter, string $id): bool => $adapter->supportsImageGeneration($id));
    }

    public function getForProvider(string $providerIdentifier): ImageProviderAdapterInterface
    {
        /** @var ImageProviderAdapterInterface */
        return $this->getFromMap($providerIdentifier, 'image generation');
    }
}
