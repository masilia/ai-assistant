<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration extends SiteAccessAware\Configuration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('masilia_ai_assistant');
        $rootNode = $treeBuilder->getRootNode();

        $systemNode = $this->generateScopeBaseNode($rootNode);
        $systemNode
            ->scalarNode('provider')
                ->defaultNull()
                ->info('Provider identifier (openai, anthropic, mistral, ollama, minimax). Used as YAML-level fallback when no DB provider is active.')
            ->end()
            ->scalarNode('api_key')
                ->defaultNull()
                ->info('API key for the provider. Only used as fallback when no DB provider is active.')
            ->end()
            ->scalarNode('api_url')
                ->defaultNull()
                ->info('Custom endpoint URL. Leave null for provider default.')
            ->end()
            ->scalarNode('model')
                ->defaultValue('gpt-4o-mini')
                ->info('Model identifier to use as fallback.')
            ->end()
            ->floatNode('temperature')
                ->defaultValue(0.7)
                ->min(0.0)->max(2.0)
            ->end()
            ->integerNode('max_tokens')
                ->defaultValue(4096)
                ->min(1)
            ->end()
        ;

        return $treeBuilder;
    }
}
