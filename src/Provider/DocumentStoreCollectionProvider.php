<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Metadata\Operation;
use EventEngine\Data\ImmutableRecord;

/**
 * @template T of ImmutableRecord
 * @extends  Provider<T>
 */
final class DocumentStoreCollectionProvider extends Provider
{
    /**
     * @param array<mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array<T>|object|null
     *
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|iterable|null
    {
        return $this->collectionProvider($operation, $uriVariables, $context);
    }
}
