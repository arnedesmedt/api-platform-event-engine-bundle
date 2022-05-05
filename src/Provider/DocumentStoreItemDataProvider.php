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
     * @param array<mixed>|int|object|string $id
     * @param array<mixed> $context
     */
    public function getItem(
        string $resourceClass,
        mixed $id,
        ?string $operationName = null,
        array $context = []
    ): ?object {
        $message = $this->message($context, $resourceClass, $operationName);

        /** @var object|null $result */
        $result = $this->eventEngine->produce($message);

        return $result;
    }
}
