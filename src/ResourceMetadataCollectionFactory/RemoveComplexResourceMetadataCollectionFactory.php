<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

use function strlen;
use function substr;

final class RemoveComplexResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        // Removed here after the cache decorator. Added before the decorator.
        if (isset($_GET['complex'])) {
            $resourceClass = substr($resourceClass, 0, -strlen('_' . $_GET['complex']));
        }

        return $this->resourceMetadataCollectionFactory->create($resourceClass);
    }
}
