<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\EventEngineBundle\Util\EventEngineUtil;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use function array_search;
use function explode;

final class ShortNameResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass) : ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        if ($resourceMetadata->getShortName() !== 'State') {
            return $resourceMetadata;
        }

        $parts = explode('\\', $resourceClass);
        /** @var int $position */
        $position = array_search('Entity', $parts);
        $shortName = $parts[$position + 1] ?? null;

        if ($shortName) {
            return $resourceMetadata->withShortName($shortName);
        }

        try {
            $aggregateRootClass = EventEngineUtil::fromStateToAggregateClass($resourceClass);
        } catch (RuntimeException | ReflectionException $e) {
            return $resourceMetadata;
        }

        return $resourceMetadata->withShortName(
            (new ReflectionClass($aggregateRootClass))->getShortName()
        );
    }
}
