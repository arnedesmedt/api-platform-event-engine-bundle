<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;

use function strlen;
use function substr;

final class RemoveComplexResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    public function __construct(private ResourceMetadataFactoryInterface $resourceMetadataFactory)
    {
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        if (isset($_GET['complex'])) {
            $resourceClass = substr($resourceClass, 0, -strlen('_' . $_GET['complex']));
        }

        return $this->resourceMetadataFactory->create($resourceClass);
    }
}
