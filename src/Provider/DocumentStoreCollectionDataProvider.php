<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use RuntimeException;

final class DocumentStoreCollectionDataProvider implements
    ContextAwareCollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    private Finder $messageFinder;
    private EventEngine $eventEngine;

    public function __construct(
        Finder $messageFinder,
        EventEngine $eventEngine
    ) {
        $this->messageFinder = $messageFinder;
        $this->eventEngine = $eventEngine;
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     *
     * @return array<ImmutableRecord>
     */
    public function getCollection(string $resourceClass, ?string $operationName = null, array $context = []) : array
    {
        $message = $this->messageFinder->byContext($context);

        return $this->eventEngine->dispatch(
            $message,
            []
        );
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []) : bool
    {
        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext($context);

            return true;
        } catch (RuntimeException $exception) {
            return false;
        }
    }
}
