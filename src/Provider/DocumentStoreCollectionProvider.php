<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Metadata\Operation;

final class DocumentStoreCollectionProvider extends Provider
{
    /**
     * @param array<mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|object|null
     *
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->collectionProvider($operation, $uriVariables, $context);
    }
}
