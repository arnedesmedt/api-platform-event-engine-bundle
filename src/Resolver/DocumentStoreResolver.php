<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\Paginator;
use ADS\Bundle\EventEngineBundle\Repository\Repository;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use EventEngine\Data\ImmutableRecord;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\OrderBy;

class DocumentStoreResolver implements MetaDataResolver
{
    protected Repository $repository;
    /** @required */
    public DocumentStoreFilterConverter $filterConverter;
    private ?OrderBy $orderBy = null;
    private ?Filter $filter = null;
    private ?int $skip = null;
    private ?int $itemsPerPage = null;
    private ?int $page = null;
    /** @var array<mixed> */
    private array $filters = [];

    /**
     * @param array<mixed> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData)
    {
        if (! isset($metaData['filters'])) {
            return $this;
        }

        $this->filters = $metaData['filters'];
        $this->orderBy = $this->filterConverter->order($this->filters);
        $this->filter = $this->filterConverter->filter($this->filters);
        $this->skip = $this->filterConverter->skip($this->filters);
        $this->itemsPerPage = $this->filterConverter->itemsPerPage($this->filters);
        $this->page = $this->filterConverter->page($this->filters);

        return $this;
    }

    public function orderBy(): ?OrderBy
    {
        return $this->orderBy;
    }

    public function filter(): ?Filter
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
     * @return array<mixed>
     */
    public function arguments(): array
    {
        return [
            $this->filter(),
            $this->skip(),
            $this->itemsPerPage(),
            $this->orderBy(),
        ];
    }

    /**
     * @return array<mixed>|Paginator<mixed>
     */
    public function __invoke()
    {
        $states = $this->repository->findDocumentStates(...$this->arguments());
        $states = $this->processStates($states);
        $totalItems = $this->repository->countDocuments(new AnyFilter());
        $skip = $this->skip();
        $itemsPerPage = $this->itemsPerPage();
        $page = $this->page();

        if (empty($skip) || empty($itemsPerPage) || empty($page)) {
            return $states;
        }

        return new Paginator(
            $states,
            $page,
            $itemsPerPage,
            $totalItems
        );
    }

    /**
     * @param array<ImmutableRecord> $states
     *
     * @return mixed
     */
    protected function processStates(array $states)
    {
        return $states;
    }
}
