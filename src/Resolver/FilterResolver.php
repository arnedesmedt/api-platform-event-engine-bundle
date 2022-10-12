<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use Closure;
use EventEngine\DocumentStore\Filter\Filter;

use function assert;

abstract class FilterResolver implements MetaDataResolver
{
    protected FilterConverter $filterConverter;
    protected mixed $orderBy = null;
    protected mixed $filter = null;
    private ?int $skip = null;
    private ?int $itemsPerPage = null;
    private ?int $page = null;
    /** @var array<string, mixed> */
    private array $requestFilters = [];
    /** @var array<string, array<string, string>> */
    private array $context = [];

    /**
     * @param array<string, array<string, array<string, string>>> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData): static
    {
        $this->context = $metaData['context'] ?? [];
        $this->requestFilters = $this->context['filters'] ?? [];

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

    public function skip(): ?int
    {
        return $this->skip;
    }

    public function setSkip(?int $skip): self
    {
        $this->skip = $skip;

        return $this;
    }

    public function itemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    public function setItemsPerPage(?int $itemsPerPage): self
    {
        $this->itemsPerPage = $itemsPerPage;

        return $this;
    }

    public function page(): ?int
    {
        return $this->page;
    }

    public function setPage(?int $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function __invoke(mixed $message): mixed
    {
        assert($message instanceof ApiPlatformMessage);

        $this
            ->setOrderBy($this->filterConverter->order($this->requestFilters))
            ->setFilter($this->filterConverter->filter($this->requestFilters, $message::__resource()))
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

    public function addFilter(Filter|Closure|null $filter = null): void
    {
    }

    /**
     * @return array<mixed>
     */
    abstract protected function collection(): array;

    /**
     * @param array<mixed> $collection
     */
    abstract protected function totalItems(array $collection): int;

    /**
     * @param array<mixed> $collection
     */
    abstract protected function result(
        array $collection,
        int $page,
        int $itemsPerPage,
        int $totalItems
    ): mixed;
}
