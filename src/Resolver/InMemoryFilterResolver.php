<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\InMemoryFilterConverter;
use ApiPlatform\State\Pagination\ArrayPaginator;
use Closure;
use RuntimeException;
use Traversable;

use function iterator_count;
use function iterator_to_array;

final class InMemoryFilterResolver extends FilterResolver
{
    /** @var iterable<mixed> */
    private iterable $collection;

    public function __construct(InMemoryFilterConverter $filterConverter)
    {
        $this->filterConverter = $filterConverter;
    }

    /** @param Traversable<mixed>|array<mixed> $collection */
    public function setCollection(iterable $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    protected function combineOrderBy(mixed $firstOrderBy, mixed $secondOrderBy): mixed
    {
        if ($firstOrderBy === null) {
            return $secondOrderBy;
        }

        if ($secondOrderBy === null) {
            return $firstOrderBy;
        }

        if ($firstOrderBy instanceof Closure && $secondOrderBy instanceof Closure) {
            return static fn (array $collection): array => $secondOrderBy($firstOrderBy($collection));
        }

        throw new RuntimeException('Cannot combine order by');
    }

    protected function combineFilters(mixed $firstFilter, mixed $secondFilter): mixed
    {
        if ($firstFilter === null) {
            return $secondFilter;
        }

        if ($secondFilter === null) {
            return $firstFilter;
        }

        if ($firstFilter instanceof Closure && $secondFilter instanceof Closure) {
            return static fn (array $collection): array => $secondFilter($firstFilter($collection));
        }

        throw new RuntimeException('Cannot combine filters');
    }

    /** @inheritDoc */
    protected function collection(): iterable
    {
        $collection = $this->collection;
        /** @var Closure|null $filter */
        $filter = $this->filter();
        /** @var Closure|null $order */
        $order = $this->orderBy();

        if ($filter instanceof Closure) {
            $collection = ($filter)($collection);
        }

        if ($order instanceof Closure) {
            $collection = ($order)($collection);
        }

        return $collection;
    }

    /** @inheritDoc */
    protected function totalItems(iterable $collection): int
    {
        return iterator_count($collection);
    }

    /** @inheritDoc */
    protected function result(iterable $collection, int $page, int $itemsPerPage, int $totalItems): mixed
    {
        return new ArrayPaginator(
            $collection instanceof Traversable ? iterator_to_array($collection) : $collection,
            ($page - 1) * $itemsPerPage,
            $itemsPerPage,
        );
    }
}
