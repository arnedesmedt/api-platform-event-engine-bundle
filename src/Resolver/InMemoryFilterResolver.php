<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\InMemoryFilterConverter;
use ApiPlatform\Core\DataProvider\ArrayPaginator;
use Closure;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

use function count;

/**
 * @template T
 * @extends FilterResolver<T>
 */
final class InMemoryFilterResolver extends FilterResolver
{
    /** @var array<JsonSchemaAwareRecord> */
    private array $states;

    public function __construct(InMemoryFilterConverter $filterConverter)
    {
        $this->filterConverter = $filterConverter;
    }

    /**
     * @param array<JsonSchemaAwareRecord> $states
     */
    public function setStates(array $states): static
    {
        $this->states = $states;

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function states(): array
    {
        $states = $this->states;
        /** @var Closure|null $filter */
        $filter = $this->filter();
        /** @var Closure|null $order */
        $order = $this->orderBy();

        if ($filter) {
            $states = ($filter)($states);
        }

        if ($order) {
            $states = ($order)($states);
        }

        return $states;
    }

    /**
     * @inheritDoc
     */
    protected function totalItems(array $states): int
    {
        return count($states);
    }

    /**
     * @inheritDoc
     */
    protected function result(array $states, int $page, int $itemsPerPage, int $totalItems): mixed
    {
        return new ArrayPaginator(
            $states,
            ($page - 1) * $itemsPerPage,
            $itemsPerPage,
        );
    }
}
