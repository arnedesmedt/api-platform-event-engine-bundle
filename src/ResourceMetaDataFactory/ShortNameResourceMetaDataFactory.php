<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetaDataFactory;

use ADS\Bundle\EventEngineBundle\Util;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ReflectionClass;
use RuntimeException;

final class ShortNameResourceMetaDataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass) : ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        if ($resourceMetadata->getShortName() !== 'State') {
            return $resourceMetadata;
        }

        try {
            $aggregateRootClass = Util::fromStateToAggregateClass($resourceClass);
        } catch (RuntimeException $e) {
            return $resourceMetadata;
        }

        return $resourceMetadata->withShortName(
            (new ReflectionClass($aggregateRootClass))->getShortName()
        );
    }
}

