<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\PartialPaginatorInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;

final class DocumentStoreCollectionDataProvider extends DataProvider implements
    ContextAwareCollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     *
     * @return array<mixed>|PartialPaginatorInterface<mixed>
     */
    public function getCollection(string $resourceClass, ?string $operationName = null, array $context = [])
    {
        $message = $this->message($context, $resourceClass, $operationName);

        if (! empty($context['filters'] ?? [])) {
            $message = $message->withAddedMetadata('context', $context);
        }

        return $this->eventEngine->dispatch($message);
    }
}
