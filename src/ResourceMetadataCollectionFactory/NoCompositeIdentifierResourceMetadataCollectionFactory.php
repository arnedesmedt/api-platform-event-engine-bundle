<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

final class NoCompositeIdentifierResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    /** @param class-string $resourceClass */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        return $this->decorated->create($resourceClass);
    }
}
