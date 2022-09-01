<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use Closure;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\OrderBy;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

use function assert;

/**
 * @template TState of JsonSchemaAwareRecord
 */
abstract class FilterResolver implements MetaDataResolver
{
    protected FilterConverter $filterConverter;
    protected OrderBy|Closure|null $orderBy = null;
    protected Filter|Closure|null $filter = null;
    private ?int $skip = null;
    private ?int $itemsPerPage = null;
    private ?int $page = null;
    /** @var array<string> */
    private array $filters = [];
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
        $this->filters = $this->context['filters'] ?? [];

        return $this;
    }

    public function orderBy(): Closure|OrderBy|null
    {
        return $this->orderBy;
    }

    public function filter(): Closure|Filter|null
    {
        return $this->filter;
    }

    public function skip(): ?int
    {
        return $this->skip;
    }

    public function itemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    public function page(): ?int
    {
        return $this->page;
    }

    public function __invoke(mixed $message): mixed
    {
        assert($message instanceof ApiPlatformMessage);

        /** @var array<array<string>> $filters */
        $filters = $this->filters;
        $this->orderBy = $this->filterConverter->order($filters);

        $filter = $this->filterConverter->filter($this->filters, $message::__resource());
        $this->filter = $this->filter instanceof Filter && $filter instanceof Filter
            ? new AndFilter($this->filter, $filter)
            : ($this->filter ?? $filter);

        /** @var array<string, int> $filters */
        $filters = $this->filters;
        $this->skip = $this->filterConverter->skip($filters);
        $this->itemsPerPage = $this->filterConverter->itemsPerPage($filters);
        $this->page = $this->filterConverter->page($filters);

        $states = $this->states();
        $totalItems = $this->totalItems($states);
        $skip = $this->skip();
        $itemsPerPage = $this->itemsPerPage();
        $page = $this->page();

        if ($skip === null || $itemsPerPage === null || $page === null) {
            return $states;
        }

        return $this->result($states, $page, $itemsPerPage, $totalItems);
    }

    /**
     * @return array<TState>
     */
    abstract protected function states(): array;

    /**
     * @param array<TState> $states
     */
    abstract protected function totalItems(array $states): int;

    /**
     * @param array<TState> $states
     */
    abstract protected function result(
        array $states,
        int $page,
        int $itemsPerPage,
        int $totalItems
    ): mixed;
}
