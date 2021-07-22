<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

abstract class FilterConverter
{
    protected FilterFinder $filterFinder;
    protected string $pageParameterName;
    protected string $itemsPerPageParameterName;
    protected string $orderParameterName;

    public function __construct(
        FilterFinder $filterFinder,
        string $pageParameterName = 'page',
        string $itemsPerPageParameterName = 'items-per-page',
        string $orderParameterName = 'order'
    ) {
        $this->filterFinder = $filterFinder;
        $this->pageParameterName = $pageParameterName;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
        $this->orderParameterName = $orderParameterName;
    }

    /**
     * @param array<mixed> $filters
     *
     * @return mixed
     */
    abstract public function order(array $filters);

    /**
     * @param array<mixed> $filters
     * @param class-string $resourceClass
     *
     * @return mixed
     */
    abstract public function filter(array $filters, string $resourceClass);

    /**
     * @param array<mixed> $filters
     *
     * @return mixed
     */
    public function skip(array $filters)
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
     * @param array<mixed> $filters
     */
    public function itemsPerPage(array $filters): ?int
    {
        if (! isset($filters[$this->itemsPerPageParameterName])) {
            return null;
        }

        return (int) $filters[$this->itemsPerPageParameterName];
    }

    /**
     * @param array<mixed> $filters
     */
    public function page(array $filters): ?int
    {
        if (! isset($filters[$this->pageParameterName])) {
            return null;
        }

        return (int) $filters[$this->pageParameterName];
    }
}
