<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class DocumentStoreCollectionDataProvider implements
    ContextAwareCollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    private DenormalizerInterface $denormalizer;
    private EventEngine $eventEngine;

    public function __construct(
        DenormalizerInterface $denormalizer,
        EventEngine $eventEngine
    ) {
        $this->denormalizer = $denormalizer;
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
        /** @var Message $message */
        $message = $this->denormalizer->denormalize([], $resourceClass, null, $context);

        return $this->eventEngine->dispatch($message);
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []) : bool
    {
        return $this->denormalizer->supportsDenormalization([], $resourceClass, null);
    }
}
