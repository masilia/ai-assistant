<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class MasiliaAiAssistantExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'MasiliaAiAssistant' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/../Entity',
                            'prefix' => 'Masilia\AiAssistant\Entity',
                            'alias' => 'MasiliaAiAssistant',
                        ],
                    ],
                ],
            ]);
        }

        if (isset($bundles['TwigBundle'])) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    __DIR__ . '/../Resources/views' => 'MasiliaAiAssistant',
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
        $loader->load('default_parameters.yaml');
    }

    public function getAlias(): string
    {
        return 'masilia_ai_assistant';
    }
}
