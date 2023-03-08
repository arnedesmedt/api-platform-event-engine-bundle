<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\Paginator;
use ADS\Bundle\EventEngineBundle\Repository\DefaultStateRepository;
use ADS\ValueObjects\Implementation\ListValue\IterableListValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\OrderBy;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

use function array_values;

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

    public function setFilter(mixed $filter): FilterResolver
    {
        if ($this->filter instanceof Filter) {
            $filter = $filter instanceof Filter
                ? new AndFilter($this->filter, $filter)
                : $this->filter;
        }

        return parent::setFilter($filter);
    }

    /** @inheritDoc */
    protected function collection(): array
    {
        /** @var Filter|null $filter */
        $filter = $this->filter();
        /** @var OrderBy|null $orderBy */
        $orderBy = $this->orderBy();

        /** @var array<TState> $items */
        $items = $this->repository
            ->findStates(
                $filter,
                $this->skip(),
                $this->itemsPerPage(),
                $orderBy,
            )
            ->toItems();

        return array_values($items);
    }

    /** @inheritDoc */
    protected function totalItems(array $collection): int
    {
        return $this->repository->countDocuments(new AnyFilter());
    }

    /** @inheritDoc */
    protected function result(array $collection, int $page, int $itemsPerPage, int $totalItems): mixed
    {
        return new Paginator(
            $collection,
            $page,
            $itemsPerPage,
            $totalItems,
        );
    }
}
