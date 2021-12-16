<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Persister;

use ADS\Bundle\EventEngineBundle\Command\Command;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use EventEngine\EventEngine;
use EventEngine\Messaging\MessageBag;

final class CommandPersister implements ContextAwareDataPersisterInterface
{
    private EventEngine $eventEngine;

    public function __construct(EventEngine $eventEngine)
    {
        $this->eventEngine = $eventEngine;
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     */
    public function supports($data, array $context = []): bool
    {
        return $data instanceof MessageBag && $data->get(MessageBag::MESSAGE) instanceof Command;
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function persist($data, array $context = [])
    {
        return $this->eventEngine->dispatch($data);
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function remove($data, array $context = [])
    {
        return $this->eventEngine->dispatch($data);
    }
}
