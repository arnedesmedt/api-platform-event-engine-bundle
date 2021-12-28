<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;

use function sprintf;

final class AddComplexResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    public function __construct(private ResourceMetadataFactoryInterface $resourceMetadataFactory)
    {
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        if (isset($_GET['complex'])) {
            $resourceClass = sprintf('%s_%s', $resourceClass, $_GET['complex']);
        }

        return $this->resourceMetadataFactory->create($resourceClass);
    }
}
