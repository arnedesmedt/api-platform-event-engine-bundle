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
     * @param mixed $id
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function getItem(
        string $resourceClass,
        $id,
        ?string $operationName = null,
        array $context = []
    ) {
        $message = $this->message($context, $resourceClass, $operationName);

        return $this->eventEngine->dispatch($message);
    }
}
