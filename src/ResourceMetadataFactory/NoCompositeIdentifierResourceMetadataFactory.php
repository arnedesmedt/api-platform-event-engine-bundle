<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Resource\NoCompositeIdentifierResource;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ReflectionClass;

use function array_merge;

final class NoCompositeIdentifierResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;

    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetaData = $this->resourceMetadataFactory->create($resourceClass);

        $reflectionClass = new ReflectionClass($resourceClass);
        if ($reflectionClass->implementsInterface(NoCompositeIdentifierResource::class)) {
            $resourceMetaData = $resourceMetaData->withAttributes(
                array_merge(
                    $resourceMetaData->getAttributes() ?? [],
                    ['composite_identifier' => false]
                )
            );
        }

        return $resourceMetaData;
    }
}
