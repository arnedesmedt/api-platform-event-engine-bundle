<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection\Compiler;

use ADS\Bundle\ApiPlatformEventEngineBundle\Loader\ImmutableRecordLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ApiPlatformEventEnginePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->immutableRecordLoader($container);

        $eventEngineMessageResourceMetadataCollectionFactoryId = 'ADS\Bundle\ApiPlatformEventEngineBundle\\' .
            'ResourceMetadataCollectionFactory\EventEngineMessageResourceMetadataCollectionFactory';
        $container->setAlias(
            'api_platform.metadata.resource.metadata_collection_factory',
            $eventEngineMessageResourceMetadataCollectionFactoryId,
        );

//        $container->getDefinition(
//            $eventEngineMessageResourceMetadataCollectionFactoryId,
//        )
//            ->replaceArgument(
//                0,
//                $container->getDefinition('api_platform.metadata.resource.metadata_collection_factory.attributes'),
//            );
    }

    /**
     * Since we don't want that the classMetadataFactory creates attribute metadata
     * for static/metadata properties, we created our own loader.
     */
    private function immutableRecordLoader(ContainerBuilder $container): void
    {
        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');
        /** @var array<int, mixed> $serializerLoaders */
        $serializerLoaders = $chainLoader->getArgument(0);
        $serializerLoaders[] = new Definition(ImmutableRecordLoader::class);
        $chainLoader->replaceArgument(0, $serializerLoaders);
    }
}
