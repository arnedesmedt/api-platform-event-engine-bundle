<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Persister;

use ADS\Bundle\EventEngineBundle\Message\Command;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use EventEngine\EventEngine;
use EventEngine\Messaging\MessageBag;
use stdClass;

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
    public function supports($data, array $context = []) : bool
    {
        return $data instanceof MessageBag && $data->get('message') instanceof Command;
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     */
    public function persist($data, array $context = []) : stdClass
    {
        $this->eventEngine->dispatch($data);

        return new stdClass();
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     */
    public function remove($data, array $context = []) : void
    {
        $this->eventEngine->dispatch($data);
    }
}
