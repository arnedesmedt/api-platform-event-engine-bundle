<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Core\DataProvider\PaginatorInterface;
use ApiPlatform\Core\DataProvider\PartialPaginatorInterface;
use ArrayObject;
use IteratorAggregate;

use function ceil;
use function count;

/**
 * @implements IteratorAggregate<mixed>
 */
class Paginator implements PartialPaginatorInterface, IteratorAggregate, PaginatorInterface
{
    /** @var mixed[] */
    private readonly array $results;

    /**
     * @param array<mixed> $results
     */
    public function __construct(
        array $results,
        private readonly int $page,
        private readonly int $itemsPerPage,
        private readonly int $totalItems
    ) {
        $this->results = $results;
    }

    /**
     * @return array<mixed>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getCurrentPage(): float
    {
        return (float) $this->page;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function getLastPage(): float
    {
        return ceil($this->getTotalItems() / $this->getItemsPerPage());
    }

    public function getTotalItems(): float
    {
        return $this->totalItems;
    }

    /**
     * @return ArrayObject<int|string, mixed>
     */
    public function getIterator(): ArrayObject
    {
        return new ArrayObject($this->results);
    }
}
