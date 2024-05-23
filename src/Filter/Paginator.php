<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ArrayObject;
use IteratorAggregate;
use Traversable;

use function ceil;
use function iterator_count;
use function iterator_to_array;

/**
 * @implements IteratorAggregate<mixed, object>
 * @implements PaginatorInterface<object>
 * @implements PartialPaginatorInterface<object>
 */
class Paginator implements PartialPaginatorInterface, IteratorAggregate, PaginatorInterface
{
    /** @param array<mixed> $collection */
    public function __construct(
        private readonly iterable $collection,
        private int $page,
        private int $itemsPerPage,
        private int $totalItems,
    ) {
    }

    /** @return iterable<mixed> */
    public function collection(): iterable
    {
        return $this->collection;
    }

    public function count(): int
    {
        return iterator_count($this->collection);
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

    /** @return ArrayObject<(int|string), mixed> */
    public function getIterator(): ArrayObject
    {
        return new ArrayObject(
            $this->collection instanceof Traversable
                ? iterator_to_array($this->collection)
                : $this->collection,
        );
    }
}
