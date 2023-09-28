<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /** @inheritDoc */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ads_api_platform_event_engine');

        /** @var ArrayNodeDefinition $arrayNodeDefinition */
        $arrayNodeDefinition = $treeBuilder->getRootNode();
        $openApi = $arrayNodeDefinition->children()->arrayNode('open_api')->addDefaultsIfNotSet();
        $openApiChildren = $openApi->children();

        $servers = $openApiChildren->arrayNode('servers')->arrayPrototype();
        $serverChildren = $servers->children();
        $serverChildren->scalarNode('url')->defaultValue('http://0.0.0.0:8001');
        $serverChildren->scalarNode('description')->defaultValue('localhost');

        $tags = $openApiChildren->arrayNode('tags')->addDefaultsIfNotSet();
        $tagsChildren = $tags->children();
        $order = $tagsChildren->arrayNode('order')->scalarPrototype();

        return $treeBuilder;
    }
}
