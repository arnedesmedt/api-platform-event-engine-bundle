<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ads_api_platform_event_engine');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $openApi = $rootNode->children()->arrayNode('open_api')->addDefaultsIfNotSet();

        $servers = $openApi->children()->arrayNode('servers')->arrayPrototype();
        $servers->children()->scalarNode('url')->defaultValue('http://0.0.0.0:8001');
        $servers->children()->scalarNode('description')->defaultValue('localhost');

        $tags = $openApi->children()->arrayNode('tags')->addDefaultsIfNotSet();
        $tags->children()->arrayNode('order')->scalarPrototype();

        return $treeBuilder;
    }
}
