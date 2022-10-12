<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resolver;

use ADS\Bundle\EventEngineBundle\Resolver\MetaDataResolver;

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
