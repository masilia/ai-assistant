<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

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
                            'prefix' => 'Masilia\Bundle\AiAssistant\Entity',
                            'alias' => 'MasiliaAiAssistant',
                        ],
                    ],
                ],
            ]);
        }

        if (isset($bundles['TwigBundle'])) {
            $configFile = __DIR__ . '/../Resources/config/twig.yaml';
            $config = Yaml::parse(file_get_contents($configFile));
            $container->prependExtensionConfig('twig', $config);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Wire openai fallback config → container parameters (overridable by env vars in default_parameters.yaml).
        foreach ($config['openai'] as $key => $value) {
            $paramName = 'masilia_ai_assistant.openai.' . $key;
            if (!$container->hasParameter($paramName)) {
                $container->setParameter($paramName, $value);
            }
        }

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
