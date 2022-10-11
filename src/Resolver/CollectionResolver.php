<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\EventEngineBundle\Repository\DefaultStateRepository;
use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use ADS\ValueObjects\Implementation\ListValue\IterableListValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

/**
 * @template TRepository of DefaultStateRepository
 * @template TStates of IterableListValue
 * @template TState of JsonSchemaAwareRecord
 * @template TId of ValueObject
 */
abstract class CollectionResolver implements MetaDataResolver
{
    /** @var TRepository<TStates, TState, TId> */
    protected DefaultStateRepository $repository;

    /**
     * @param DocumentStoreFilterResolver<TStates, TState, TId> $documentStoreFilterResolver
     */
    public function __construct(private readonly DocumentStoreFilterResolver $documentStoreFilterResolver)
    {
    }

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
