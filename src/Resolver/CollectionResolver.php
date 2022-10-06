<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\EventEngineBundle\Aggregate\AggregateRoot;
use ADS\Bundle\EventEngineBundle\Repository\Repository;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use ADS\ValueObjects\Implementation\ListValue\IterableListValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

/**
 * @template TRepository of Repository
 * @template TAgg of AggregateRoot
 * @template TStates of IterableListValue
 * @template TState of JsonSchemaAwareRecord
 * @template TId of ValueObject
 */
abstract class CollectionResolver implements MetaDataResolver
{
    /**
     * @var DocumentStoreFilterResolver<TAgg, TStates, TState, TId>
     * @required
     */
    public DocumentStoreFilterResolver $documentStoreFilterResolver;

    /** @var TRepository<TAgg, TStates, TState, TId> */
    protected Repository $repository;

    /**
     * @param array<string, array<string, array<string, string>>> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData): static
    {
        $this->documentStoreFilterResolver->setMetaData($metaData);

        return $this;
    }

    public function __invoke(mixed $message): mixed
    {
        $this->documentStoreFilterResolver->setRepository($this->repository);

        return ($this->documentStoreFilterResolver)($message);
    }
}
