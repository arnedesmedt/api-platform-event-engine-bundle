<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Core\DataProvider\PaginatorInterface;
use ApiPlatform\Core\DataProvider\PartialPaginatorInterface;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use IteratorAggregate;

use function ceil;
use function count;

/**
 * @template TState of JsonSchemaAwareRecord
 * @implements IteratorAggregate<mixed, mixed>
 */
class Paginator implements PartialPaginatorInterface, IteratorAggregate, PaginatorInterface
{
    /**
     * @var array<TState>
     * @readonly
     */
    private array $results;

    /**
     * @param array<TState> $results
     */
    public function __construct(
        array $results,
        private int $page,
        private int $itemsPerPage,
        private int $totalItems
    ) {
        $this->results = $results;
    }

    /**
     * @return array<TState>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getCurrentPage(): float
    {
        return (float) $this->page;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function getLastPage(): float
    {
        return ceil($this->getTotalItems() / $this->getItemsPerPage());
    }

    public function getTotalItems(): float
    {
        return $this->totalItems;
    }

    /**
     * @return ArrayObject<(int|string), TState>
     */
    public function getIterator(): ArrayObject
    {
        return new ArrayObject($this->results);
    }
}
