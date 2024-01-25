<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineState;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

class RemoveOperationsResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);

        foreach ($resourceMetadataCollection as &$resource) {
            if (! $resource instanceof EventEngineState) {
                continue;
            }

            $resource = $resource->withOperations(new Operations());
        }

        return $resourceMetadataCollection;
    }
}
