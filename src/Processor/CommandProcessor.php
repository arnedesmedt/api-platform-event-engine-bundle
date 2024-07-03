<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** @implements ProcessorInterface<Message,array<mixed>|object|null> */
#[AutoconfigureTag('api_platform.state_processor')]
final class CommandProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire('@ADS\Bundle\EventEngineBundle\Messenger\MessengerMessageProducer')]
        private MessageProducer $eventEngine,
    ) {
    }

    /**
     * @param array<mixed> $uriVariables
     * @param array<string, mixed> $context
     * @param Message $data
     *
     * @return array<mixed>|object|null
     *
     * @inheritDoc
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var array<mixed>|object|null $result */
        $result = $this->eventEngine->produce($data);

        return $result;
    }
}
