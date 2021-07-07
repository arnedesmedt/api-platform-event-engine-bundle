<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ApiPlatform\Core\Api\OperationType;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;

abstract class DataProvider
{
    protected EventEngine $eventEngine;

    public function __construct(EventEngine $eventEngine)
    {
        $this->eventEngine = $eventEngine;
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
}
