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
    abstract public function skip(array $filters);

    /**
     * @param array<mixed> $filters
     *
     * @return mixed
     */
    abstract public function limit(array $filters);
}
