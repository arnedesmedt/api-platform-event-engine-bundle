<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\Paginator;
use ADS\Bundle\EventEngineBundle\Repository\DefaultStateRepository;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\AndOrder;
use EventEngine\DocumentStore\OrderBy\OrderBy;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use RuntimeException;
use TeamBlue\ValueObjects\Implementation\ListValue\IterableListValue;
use TeamBlue\ValueObjects\ValueObject;

/**
 * @template TStates of IterableListValue
 * @template TState of JsonSchemaAwareRecord
 * @template TId of ValueObject
 */
final class StatesFilterResolver extends FilterResolver
{
    /** @var DefaultStateRepository<TStates, TState, TId> */
    protected DefaultStateRepository $repository;

    public function __construct(DocumentStoreFilterConverter $filterConverter)
    {
        $this->filterConverter = $filterConverter;
    }

    /**
     * @param DefaultStateRepository<TStates, TState, TId> $repository
     *
     * @return self<TStates, TState, TId>
     */
    public function setRepository(DefaultStateRepository $repository): self
    {
        $this->repository = $repository;

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

        if ($firstOrderBy instanceof OrderBy && $secondOrderBy instanceof OrderBy) {
            return AndOrder::by($firstOrderBy, $secondOrderBy);
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

        if ($firstFilter instanceof Filter && $secondFilter instanceof Filter) {
            return new AndFilter($firstFilter, $secondFilter);
        }

        throw new RuntimeException('Cannot combine filters');
    }

    /** @inheritDoc */
    protected function collection(): iterable
    {
        /** @var Filter|null $filter */
        $filter = $this->filter();
        /** @var OrderBy|null $orderBy */
        $orderBy = $this->orderBy();

        /** @var TStates $items */
        $items = $this->repository
            ->findStates(
                $filter,
                $this->skip(),
                $this->itemsPerPage(),
                $orderBy,
            );

        return $items->values();
    }

    /** @inheritDoc */
    protected function totalItems(iterable $collection): int
    {
        return $this->repository->countDocuments(new AnyFilter());
    }

    /** @inheritDoc */
    protected function result(iterable $collection, int $page, int $itemsPerPage, int $totalItems): mixed
    {
        return new Paginator(
            $collection,
            $page,
            $itemsPerPage,
            $totalItems,
        );
    }
}
