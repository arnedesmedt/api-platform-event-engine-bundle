<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use Closure;
use EventEngine\Data\ImmutableRecord;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\OrderBy;

abstract class FilterResolver implements MetaDataResolver
{
    protected FilterConverter $filterConverter;
    /** @var OrderBy|Closure|null  */
    protected $orderBy = null;
    /** @var Filter|Closure|null */
    protected $filter = null;
    private ?int $skip = null;
    private ?int $itemsPerPage = null;
    private ?int $page = null;
    /** @var array<mixed> */
    private array $filters = [];
    /** @var array<mixed> */
    private array $context = [];

    /**
     * @param array<mixed> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData)
    {
        $this->context = $metaData['context'] ?? [];
        $this->filters = $this->context['filters'] ?? [];

        return $this;
    }

    /**
     * @return Closure|OrderBy|null
     */
    public function orderBy()
    {
        return $this->orderBy;
    }

    /**
     * @return Closure|Filter|null
     */
    public function filter()
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

    /**
     * @return mixed
     */
    public function __invoke(ApiPlatformMessage $message)
    {
        $this->orderBy = $this->filterConverter->order($this->filters);
        $filter = $this->filterConverter->filter($this->filters, $message::__entity());
        $this->filter = $this->filter instanceof Filter && $filter !== null
            ? new AndFilter($this->filter, $filter)
            : ($this->filter ?? $filter);
        $this->skip = $this->filterConverter->skip($this->filters);
        $this->itemsPerPage = $this->filterConverter->itemsPerPage($this->filters);
        $this->page = $this->filterConverter->page($this->filters);

        $states ??= $this->states();
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
     * @return array<ImmutableRecord>
     */
    protected function states(): array
    {
        return [];
    }

    /**
     * @param array<ImmutableRecord> $states
     */
    abstract protected function totalItems(array $states): int;

    /**
     * @param array<ImmutableRecord> $states
     *
     * @return mixed
     */
    abstract protected function result(
        array $states,
        int $page,
        int $itemsPerPage,
        int $totalItems
    );
}
