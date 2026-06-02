<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('masilia_ai_assistant');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('openai')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('api_key')->defaultValue('')->end()
                        ->scalarNode('model')->defaultValue('gpt-4o-mini')->end()
                        ->floatNode('temperature')->defaultValue(0.7)->end()
                        ->integerNode('max_tokens')->defaultValue(4096)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
