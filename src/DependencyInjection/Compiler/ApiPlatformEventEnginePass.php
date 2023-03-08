<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection\Compiler;

use ADS\Bundle\ApiPlatformEventEngineBundle\Loader\ImmutableRecordLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ApiPlatformEventEnginePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->immutableRecordLoader($container);
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

        $immutableRecordLoader = new Definition(
            ImmutableRecordLoader::class,
            [
                new Reference('annotation_reader', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $serializerLoaders[0],
            ],
        );
        $immutableRecordLoader->setPublic(false);

        $chainLoader->replaceArgument(0, [$immutableRecordLoader]);
        $container->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);
    }
}
