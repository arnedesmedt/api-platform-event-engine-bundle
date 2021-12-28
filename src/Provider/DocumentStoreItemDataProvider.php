<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Core\DataProvider\DenormalizedIdentifiersAwareItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;

final class DocumentStoreItemDataProvider extends DataProvider implements
    DenormalizedIdentifiersAwareItemDataProviderInterface,
    RestrictedDataProviderInterface
{
    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function getItem(
        string $resourceClass,
        mixed $id,
        ?string $operationName = null,
        array $context = []
    ): mixed {
        $message = $this->message($context, $resourceClass, $operationName);

        return $this->eventEngine->dispatch($message);
    }
}
