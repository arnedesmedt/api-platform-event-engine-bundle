<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function intval;

abstract class FilterConverter
{
    public function __construct(
        protected FilterFinder $filterFinder,
        #[Autowire('%api_platform.collection.pagination.page_parameter_name%')]
        protected string $pageParameterName = 'page',
        #[Autowire('%api_platform.collection.pagination.items_per_page_parameter_name%')]
        protected string $itemsPerPageParameterName = 'items-per-page',
        #[Autowire('%api_platform.collection.order_parameter_name%')]
        protected string $orderParameterName = 'order',
    ) {
    }

    /** @param array<string, mixed> $filters */
    abstract public function order(array $filters): mixed;

    /**
     * @param array<string, mixed> $filters
     * @param class-string $resourceClass
     */
    abstract public function filter(array $filters, Operation $operation, string $resourceClass): mixed;

    /** @param array<string, mixed> $filters */
    public function skip(array $filters): int|null
    {
        if (
            $this->page($filters) === null
            || $this->itemsPerPage($filters) === null
        ) {
            return null;
        }

        return ($this->page($filters) - 1) * $this->itemsPerPage($filters);
    }

    /** @param array<string, mixed> $filters */
    public function itemsPerPage(array $filters): int|null
    {
        if (! isset($filters[$this->itemsPerPageParameterName])) {
            return null;
        }

        return intval($filters[$this->itemsPerPageParameterName]); // @phpstan-ignore-line
    }

    /** @param array<string, mixed> $filters */
    public function page(array $filters): int|null
    {
        if (! isset($filters[$this->pageParameterName])) {
            return null;
        }

        return intval($filters[$this->pageParameterName]); // @phpstan-ignore-line
    }
}
