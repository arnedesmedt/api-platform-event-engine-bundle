<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\Paginator;
use ADS\Bundle\EventEngineBundle\Repository\Repository;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\Filter;

final class DocumentStoreFilterResolver extends FilterResolver
{
    protected Repository $repository;

    public function __construct(DocumentStoreFilterConverter $filterConverter)
    {
        $this->filterConverter = $filterConverter;
    }

    public function setRepository(Repository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

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
     * @inheritDoc
     */
    protected function states(): array
    {
        return $this->repository->findDocumentStates(...$this->arguments());
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
    protected function result(array $states, int $page, int $itemsPerPage, int $totalItems)
    {
        return new Paginator(
            $states,
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
