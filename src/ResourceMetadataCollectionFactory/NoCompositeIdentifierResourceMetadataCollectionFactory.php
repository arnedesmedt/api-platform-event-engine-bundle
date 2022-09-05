<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Resource\NoCompositeIdentifierResource;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ReflectionClass;

final class NoCompositeIdentifierResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        $reflectionClass = new ReflectionClass($resourceClass);
        if (! $reflectionClass->implementsInterface(NoCompositeIdentifierResource::class)) {
            return $resourceMetadataCollection;
        }

        foreach ($resourceMetadataCollection as &$resourceMetadata) {
            /** @var ApiResource $resourceMetadata */
            $resourceMetadata = (new ApiResource(compositeIdentifier: false))->withResource($resourceMetadata);
        }

        return $resourceMetadataCollection;
    }
}
