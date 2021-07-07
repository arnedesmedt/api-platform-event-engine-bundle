<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\Paginator;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Repository\Repository;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
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
        $this->orderBy = $this->filterConverter->order($this->filters);
        $this->filter = $this->filterConverter->filter($this->filters, $this->repository->stateClass());
        $this->skip = $this->filterConverter->skip($this->filters);
        $this->itemsPerPage = $this->filterConverter->itemsPerPage($this->filters);
        $this->page = $this->filterConverter->page($this->filters);

        return $this;
    }

    public function orderBy(): ?OrderBy
    {
        return $this->orderBy;
    }

    public function filter(?ApiPlatformMessage $query = null): ?Filter
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
    public function arguments(?ApiPlatformMessage $query = null): array
    {
        return [
            $this->filter($query),
            $this->skip(),
            $this->itemsPerPage(),
            $this->orderBy(),
        ];
    }

    /**
     * @return array<mixed>|Paginator<mixed>
     */
    public function __invoke(?ApiPlatformMessage $query = null)
    {
        $states = $this->repository->findDocumentStates(...$this->arguments($query));
        $totalItems = $this->repository->countDocuments(new AnyFilter());
        $skip = $this->skip();
        $itemsPerPage = $this->itemsPerPage();
        $page = $this->page();

        if ($skip === null || $itemsPerPage === null || $page === null) {
            return $states;
        }

        return new Paginator(
            $states,
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
