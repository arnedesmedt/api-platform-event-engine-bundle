<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ApiPlatform\State\ProviderInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;

/**
 * @template T of ImmutableRecord
 * @implements ProviderInterface<T>
 */
abstract class Provider implements ProviderInterface
{
    public function __construct(protected MessageProducer $eventEngine)
    {
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
