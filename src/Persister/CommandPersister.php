<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Persister;

use ADS\Bundle\EventEngineBundle\Command\Command;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;

final class CommandPersister implements ContextAwareDataPersisterInterface
{
    public function __construct(private EventEngine $eventEngine)
    {
    }

    /**
     * @param array<mixed> $context
     */
    public function supports(mixed $data, array $context = []): bool
    {
        return $data instanceof MessageBag && $data->get(MessageBag::MESSAGE) instanceof Command;
    }

    /**
     * @param Message|class-string $data
     * @param array<mixed> $context
     */
    public function persist(mixed $data, array $context = []): object
    {
        /** @var object $result */
        $result = $this->eventEngine->dispatch($data);

        return $result;
    }

    /**
     * @param Message|class-string $data
     * @param array<mixed> $context
     */
    public function remove(mixed $data, array $context = []): mixed
    {
        return $this->eventEngine->dispatch($data);
    }
}
