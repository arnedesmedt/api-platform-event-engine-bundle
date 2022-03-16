<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\DataProvider\PartialPaginatorInterface;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;

abstract class DataProvider
{
    public function __construct(protected MessageProducer $eventEngine)
    {
    }

    /**
     * @param array<mixed> $context
     */
    protected function message(array $context, string $resourceClass, ?string $operationName): Message
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

        return $message;
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []): bool
    {
        return (bool) ($context['message'] ?? null);
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     *
     * @return array<mixed>|PartialPaginatorInterface<mixed>
     */
    protected function collectionProvider(
        string $resourceClass,
        ?string $operationName = null,
        array $context = []
    ): array|PartialPaginatorInterface {
        $message = $this->message($context, $resourceClass, $operationName);

        if (! empty($context['filters'] ?? [])) {
            $message = $message->withAddedMetadata('context', $context);
        }

        /** @var array<mixed>|PartialPaginatorInterface<mixed> $result */
        $result = $this->eventEngine->produce($message);

        return $result;
    }
}
