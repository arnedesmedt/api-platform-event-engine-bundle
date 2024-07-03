<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Metadata\FilterInterface;
use ApiPlatform\Metadata\Operation;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FilterFinder
{
    public function __construct(
        #[Autowire('@api_platform.filter_locator')]
        private ContainerInterface $filterLocator,
    ) {
    }

    public function __invoke(Operation $operation, string $type): FilterInterface|null
    {
        $filterIds = $operation->getFilters() ?? [];

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
