<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\ConfigurationProcessor;
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

        // Register a dedicated Monolog channel so AI logs can be routed
        // independently (e.g. to a separate file, Datadog, Sentry). The
        // host app's monolog config can apply handlers, filters, and
        // formatters to the 'ai' channel without touching the default one.
        $container->prependExtensionConfig('monolog', [
            'channels' => ['ai'],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
        $loader->load('default_settings.yaml');

        // Map siteaccess-aware settings → scoped container parameters
        if (!empty($config['system'])) {
            $processor = new ConfigurationProcessor($container, $this->getAlias());
            $processor->mapSetting('provider', $config);
            $processor->mapSetting('api_key', $config);
            $processor->mapSetting('api_url', $config);
            $processor->mapSetting('model', $config);
            $processor->mapSetting('temperature', $config);
            $processor->mapSetting('max_tokens', $config);
        }
    }

    public function getAlias(): string
    {
        return 'masilia_ai_assistant';
    }
}
