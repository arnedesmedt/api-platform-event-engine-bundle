<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\EventEngineBundle\Query\Query;
use ADS\Bundle\EventEngineBundle\Repository\DefaultStateRepository;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use ADS\ValueObjects\Implementation\ListValue\IterableListValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

/**
 * @template TStates of IterableListValue
 * @template TState of JsonSchemaAwareRecord
 * @template TId of ValueObject
 */
abstract class StatesResolver implements MetaDataResolver
{
    /** @var DefaultStateRepository<TStates, TState, TId> */
    protected DefaultStateRepository $repository;

    /**
     * @var StatesFilterResolver<TStates, TState, TId> $statesFilterResolver
     * @required
     */
    public StatesFilterResolver $statesFilterResolver;

    /**
     * @param array<string, array<string, array<string, string>>> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData): static
    {
        $this->statesFilterResolver->setMetaData($metaData);

        return $this;
    }

    public function __invoke(Query $message): mixed
    {
        $this->statesFilterResolver->setRepository($this->repository);

        return ($this->statesFilterResolver)($message);
    }
}
