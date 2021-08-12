<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;

use function strlen;
use function substr;

final class RemoveComplexResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;

    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        if (isset($_GET['complex'])) {
            $resourceClass = substr($resourceClass, -strlen('_' . $_GET['complex']));
        }

        return $this->resourceMetadataFactory->create($resourceClass);
    }
}
