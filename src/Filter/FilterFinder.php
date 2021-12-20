<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Core\Api\FilterInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use Psr\Container\ContainerInterface;

class FilterFinder
{
    public function __construct(
        private readonly ResourceMetadataFactoryInterface $resourceMetadataFactory,
        private readonly ContainerInterface $filterLocator
    ) {
    }

    public function __invoke(string $resourceClass, string $type): ?FilterInterface
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        $filterIds = $resourceMetadata->getAttribute('filters', []);

        foreach ($filterIds as $filterId) {
            if (! $this->filterLocator->has($filterId)) {
                continue;
            }

            /** @var FilterInterface $filter */
            $filter = $this->filterLocator->get($filterId);

            if (! $filter instanceof $type) {
                continue;
            }

            return $filter;
        }

        return null;
    }
}
