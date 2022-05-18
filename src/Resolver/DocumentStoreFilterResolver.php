<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\Paginator;
use ADS\Bundle\EventEngineBundle\Aggregate\AggregateRoot;
use ADS\Bundle\EventEngineBundle\Repository\Repository;
use ADS\ValueObjects\Implementation\ListValue\IterableListValue;
use ADS\ValueObjects\ValueObject;
use Closure;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

use function assert;

/**
 * @template TAgg of AggregateRoot
 * @template TStates of IterableListValue
 * @template TState of JsonSchemaAwareRecord
 * @template TId of ValueObject
 * @template-extends FilterResolver<TState>
 */
final class DocumentStoreFilterResolver extends FilterResolver
{
    /** @var Repository<TAgg, TStates, TState, TId> */
    protected Repository $repository;

    public function __construct(DocumentStoreFilterConverter $filterConverter)
    {
        $this->filterConverter = $filterConverter;
    }

    /**
     * @param Repository<TAgg, TStates, TState, TId> $repository
     *
     * @return self<TAgg, TStates, TState, TId>
     */
    public function setRepository(Repository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @return self<TAgg, TStates, TState, TId>
     */
    public function addFilter(Filter $filter): self
    {
        if ($this->filter instanceof Filter) {
            $this->filter = new AndFilter($this->filter, $filter);

            return $this;
        }

        $this->filter = $filter;

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function states(): array
    {
        $filter = $this->filter();
        assert(! $filter instanceof Closure);
        $orderBy = $this->orderBy();
        assert(! $orderBy instanceof Closure);

        /** @var array<TState> $items */
        $items = $this->repository
            ->findDocumentStates(
                $filter,
                $this->skip(),
                $this->itemsPerPage(),
                $orderBy
            )
            ->toItems();

        return $items;
    }

    /**
     * @inheritDoc
     */
    protected function totalItems(array $states): int
    {
        return $this->repository->countDocuments(new AnyFilter());
    }

    /**
     * @inheritDoc
     */
    protected function result(array $states, int $page, int $itemsPerPage, int $totalItems): mixed
    {
        return new Paginator(
            $states,
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
