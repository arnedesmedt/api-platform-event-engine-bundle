<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection\Compiler\ApiPlatformEventEnginePass;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ADSApiPlatformEventEngineBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ApiPlatformEventEnginePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $rootNode->children()->booleanNode('use_metadata_resource_collection_cache')->defaultTrue();

        $openApi = $rootNode->children()->arrayNode('open_api')->addDefaultsIfNotSet();

        $servers = $openApi->children()->arrayNode('servers')->arrayPrototype();
        $servers->children()->scalarNode('url')->defaultValue('http://0.0.0.0:8001');
        $servers->children()->scalarNode('description')->defaultValue('localhost');

        $tags = $openApi->children()->arrayNode('tags')->addDefaultsIfNotSet();
        $tags->children()->arrayNode('order')->scalarPrototype();
    }

    /**
     * phpcs:disable Generic.Files.LineLength.TooLong
     *
     * @param array{"open_api": array{"servers": array<string>, "tags": array<string>}, "use_metadata_resource_collection_cache": bool} $config
     *
     *  phpcs:enable Generic.Files.LineLength.TooLong
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $builder->setParameter(
            'api_platform_event_engine.open_api.servers',
            $config['open_api']['servers'],
        );

        $builder->setParameter(
            'api_platform_event_engine.open_api.tags',
            $config['open_api']['tags'],
        );

        $builder->setParameter(
            'api_platform_event_engine.use_metadata_resource_collection_cache',
            $config['use_metadata_resource_collection_cache'],
        );

        $loader = new YamlFileLoader(
            $builder,
            new FileLocator(__DIR__ . '/../Resources/config'),
        );

        $loader->load('api_platform_event_engine.yaml');
    }
}
