<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /** @inheritDoc */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ads_api_platform_event_engine');

        $treeBuilder
            ->getRootNode()
            ->children()
                ->arrayNode('open_api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('servers')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('url')->defaultValue('http://0.0.0.0:8001')->end()
                                    ->scalarNode('description')->defaultValue('localhost')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('tags')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('order')
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
