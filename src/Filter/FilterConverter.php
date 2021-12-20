<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use Closure;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\OrderBy;

use function intval;

abstract class FilterConverter
{
    public function __construct(
        protected FilterFinder $filterFinder,
        protected string $pageParameterName = 'page',
        protected string $itemsPerPageParameterName = 'items-per-page',
        protected string $orderParameterName = 'order'
    ) {
    }

    /**
     * @param array<array<string>> $filters
     */
    abstract public function order(array $filters): OrderBy|Closure|null;

    /**
     * @param array<string> $filters
     * @param class-string $resourceClass
     */
    abstract public function filter(array $filters, string $resourceClass): Filter|Closure|null;

    /**
     * @param array<string, int> $filters
     */
    public function skip(array $filters): ?int
    {
        if (
            $this->page($filters) === null
            || $this->itemsPerPage($filters) === null
        ) {
            return null;
        }

        return ($this->page($filters) - 1) * $this->itemsPerPage($filters);
    }

    /**
     * @param array<string, int> $filters
     */
    public function itemsPerPage(array $filters): ?int
    {
        if (! isset($filters[$this->itemsPerPageParameterName])) {
            return null;
        }

        return intval($filters[$this->itemsPerPageParameterName]);
    }

    /**
     * @param array<string, int> $filters
     */
    public function page(array $filters): ?int
    {
        if (! isset($filters[$this->pageParameterName])) {
            return null;
        }

        return intval($filters[$this->pageParameterName]);
    }
}
