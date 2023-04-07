<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

use function sprintf;

final class AddComplexResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        // Added here to create a different cache. Removed after the cache decorator.
        if (isset($_GET['complex']) || isset($_ENV['complex'])) {
            $resourceClass = sprintf('%s_%s', $resourceClass, $_GET['complex'] ?? $_ENV['complex']);
        }

        return $this->resourceMetadataCollectionFactory->create($resourceClass);
    }
}
