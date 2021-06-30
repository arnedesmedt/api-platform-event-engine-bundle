<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\PartialPaginatorInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;

final class DocumentStoreCollectionDataProvider implements
    ContextAwareCollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    private EventEngine $eventEngine;

    public function __construct(EventEngine $eventEngine)
    {
        $this->eventEngine = $eventEngine;
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     *
     * @return array<mixed>|PartialPaginatorInterface<mixed>
     */
    public function getCollection(string $resourceClass, ?string $operationName = null, array $context = [])
    {
        /** @var Message|null $message */
        $message = $context['message'] ?? null;

        if ($message === null) {
            throw FinderException::noMessageFound(
                $resourceClass,
                OperationType::COLLECTION,
                $operationName
            );
        }

        if (! empty($context['filters'] ?? [])) {
            $message = $message->withAddedMetadata('filters', $context['filters']);
        }

        return $this->eventEngine->dispatch($message);
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []): bool
    {
        return (bool) ($context['message'] ?? null);
    }
}
