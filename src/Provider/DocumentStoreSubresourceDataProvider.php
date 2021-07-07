<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\DataProvider\SubresourceDataProviderInterface;

final class DocumentStoreSubresourceDataProvider extends DataProvider implements
    SubresourceDataProviderInterface,
    RestrictedDataProviderInterface
{
    /**
     * @param array<string, mixed> $identifiers
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    public function getSubresource(
        string $resourceClass,
        array $identifiers,
        array $context,
        ?string $operationName = null
    ) {
        $message = $this->message($context, $resourceClass, $operationName);

        if (! empty($context['filters'] ?? [])) {
            $message = $message->withAddedMetadata('filters', $context['filters']);
        }

        return $this->eventEngine->dispatch($message);
    }
}
