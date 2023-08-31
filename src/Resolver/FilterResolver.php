<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use ApiPlatform\Metadata\Operation;

use function assert;

abstract class FilterResolver implements MetaDataResolver
{
    protected FilterConverter $filterConverter;
    protected mixed $orderBy = null;
    protected mixed $filter = null;
    private int|null $skip = null;
    private int|null $itemsPerPage = null;
    private int|null $page = null;
    /** @var array<string, mixed> */
    private array $requestFilters = [];
    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @param array<string, array<string, mixed>> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData): static
    {
        $this->context = $metaData['context'] ?? [];
        /** @var array<string, mixed> $filters */
        $filters = $this->context['filters'] ?? [];
        $this->requestFilters = $filters;

        return $this;
    }

    public function orderBy(): mixed
    {
        return $this->orderBy;
    }

    public function setOrderBy(mixed $orderBy): self
    {
        $this->orderBy = $orderBy;

        return $this;
    }

    public function filter(): mixed
    {
        return $this->filter;
    }

    public function setFilter(mixed $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    public function skip(): int|null
    {
        return $this->skip;
    }

    public function setSkip(int|null $skip): self
    {
        $this->skip = $skip;

        return $this;
    }

    public function itemsPerPage(): int|null
    {
        return $this->itemsPerPage;
    }

    public function setItemsPerPage(int|null $itemsPerPage): self
    {
        $this->itemsPerPage = $itemsPerPage;

        return $this;
    }

    public function page(): int|null
    {
        return $this->page;
    }

    public function setPage(int|null $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function __invoke(mixed $message): mixed
    {
        assert($message instanceof ApiPlatformMessage);

        /** @var Operation $operation */
        $operation = $this->context['operation'];
        $requestOrderBy = $this->filterConverter->order($this->requestFilters);
        $requestFilter = $this->filterConverter->filter($this->requestFilters, $operation, $message::__resource());

        $this
            ->setOrderBy($this->combineOrderBy($requestOrderBy, $this->orderBy))
            ->setFilter($this->combineFilters($requestFilter, $this->filter))
            ->setSkip($this->filterConverter->skip($this->requestFilters))
            ->setItemsPerPage($this->filterConverter->itemsPerPage($this->requestFilters))
            ->setPage($this->filterConverter->page($this->requestFilters));

        $collection = $this->collection();
        $totalItems = $this->totalItems($collection);
        $skip = $this->skip();
        $itemsPerPage = $this->itemsPerPage();
        $page = $this->page();

        if ($skip === null || $itemsPerPage === null || $page === null) {
            return $collection;
        }

        return $this->result($collection, $page, $itemsPerPage, $totalItems);
    }

    abstract protected function combineOrderBy(mixed $firstOrderBy, mixed $secondOrderBy): mixed;

    abstract protected function combineFilters(mixed $firstFilter, mixed $secondFilter): mixed;

    /** @return array<mixed> */
    abstract protected function collection(): array;

    /** @param array<mixed> $collection */
    abstract protected function totalItems(array $collection): int;

    /** @param array<mixed> $collection */
    abstract protected function result(
        array $collection,
        int $page,
        int $itemsPerPage,
        int $totalItems,
    ): mixed;
}
