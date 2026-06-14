<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware;
use Masilia\AiAssistant\AiDefaults;
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
                ->defaultValue(AiDefaults::MODEL)
                ->info('Model identifier to use as fallback.')
            ->end()
            ->floatNode('temperature')
                ->defaultValue(AiDefaults::TEMPERATURE)
                ->min(0.0)->max(2.0)
            ->end()
            ->integerNode('max_tokens')
                ->defaultValue(AiDefaults::MAX_TOKENS)
                ->min(1)
            ->end()
            ->scalarNode('image_model')
                ->defaultNull()
                ->info('Model identifier for image generation (e.g. gpt-image-2, image-01).')
            ->end()
            ->scalarNode('site_content_type')
                ->defaultValue('site')
                ->info('Content type identifier for the site container.')
            ->end()
            ->scalarNode('home_page_content_type')
                ->defaultValue('home_page')
                ->info('Content type identifier for the home page.')
            ->end()
            ->scalarNode('page_content_type')
                ->defaultValue('page')
                ->info('Content type identifier for pages.')
            ->end()
            ->scalarNode('layout_content_type')
                ->defaultValue('layout_config')
                ->info('Content type identifier for layout configuration.')
            ->end()
            ->scalarNode('folder_content_type')
                ->defaultValue('folder')
                ->info('Content type identifier for folders.')
            ->end()
            ->integerNode('media_root_location_id')
                ->defaultValue(43)
                ->info('Location ID of the media root (default: 43, Ibexa standard).')
            ->end()
        ;

        return $treeBuilder;
    }
}
