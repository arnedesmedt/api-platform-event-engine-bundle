<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;

abstract class Provider implements ProviderInterface
{
    public function __construct(protected MessageProducer $eventEngine)
    {
    }

    /**
     * @param array<mixed> $uriVariables
     * @param array<mixed> $context
     *
     * @return array<mixed>|object
     */
    protected function collectionProvider(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|object {
        $message = $this->needMessage($context, $operation->getName());

        if (! empty($context['filters'] ?? [])) {
            $message = $message->withAddedMetadata('context', $context);
        }

        /** @var array<mixed>|object $result */
        $result = $this->eventEngine->produce($message);

        return $result;
    }

    /**
     * @param array<mixed> $context
     */
    protected function needMessage(array $context, ?string $operationName): Message
    {
        /** @var Message|null $message */
        $message = $context['message'] ?? null;

        if ($message === null) {
            throw FinderException::noMessageFound($operationName);
        }

        return $message;
    }
}
