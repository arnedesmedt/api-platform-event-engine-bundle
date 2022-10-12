<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;
use ADS\ValueObjects\Implementation\ListValue\IterableListValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

/**
 * @template TStates of IterableListValue
 * @template TState of JsonSchemaAwareRecord
 * @template TId of ValueObject
 */
abstract class ListResolver implements MetaDataResolver
{
    public function __construct(private readonly InMemoryFilterResolver $inMemoryFilterResolver)
    {
    }

    /**
     * @param array<string, array<string, array<string, string>>> $metaData
     *
     * @inheritDoc
     */
    public function setMetaData(array $metaData): static
    {
        $this->inMemoryFilterResolver->setMetaData($metaData);

        return $this;
    }

    public function __invoke(mixed $message): mixed
    {
        $this->inMemoryFilterResolver->setCollection($this->collection($message));

        return ($this->inMemoryFilterResolver)($message);
    }

    /**
     * @return array<mixed>
     */
    abstract protected function collection(mixed $message): array;
}
