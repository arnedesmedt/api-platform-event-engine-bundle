<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\DocumentStoreFilterConverter;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\OrderBy;

class DocumentStoreResolver implements MetaDataResolver
{
    private DocumentStoreFilterConverter $filterConverter;
    private ?OrderBy $orderBy = null;
    private ?Filter $filter = null;
    private ?int $skip = null;
    private ?int $limit = null;

    public function __construct(DocumentStoreFilterConverter $filterConverter)
    {
        $this->filterConverter = $filterConverter;
    }

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

        $filters = $metaData['filters'];
        $this->orderBy = $this->filterConverter->order($filters);
        $this->filter = $this->filterConverter->filter($filters);
        $this->skip = $this->filterConverter->skip($filters);
        $this->limit = $this->filterConverter->limit($filters);

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

    public function limit(): ?int
    {
        return $this->limit;
    }

    /**
     * @return array<mixed>
     */
    public function arguments(): array
    {
        return [
            $this->filter(),
            $this->skip(),
            $this->limit(),
            $this->orderBy(),
        ];
    }
}
