<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Psr\Container\ContainerInterface;

class FilterFinder
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private ContainerInterface $filterLocator
    ) {
    }

    public function __invoke(string $resourceClass, string $type): ?FilterInterface
    {
        $resourceMetadataCollection = $this->resourceMetadataFactory->create($resourceClass);

        /** @var ApiResource $resource */
        foreach ($resourceMetadataCollection as $resource) {
            $filterIds = $resource->getFilters() ?? [];

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
        }

        return null;
    }
}
