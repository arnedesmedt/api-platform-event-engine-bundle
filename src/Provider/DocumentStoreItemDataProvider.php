<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Core\DataProvider\DenormalizedIdentifiersAwareItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class DocumentStoreItemDataProvider implements
    DenormalizedIdentifiersAwareItemDataProviderInterface,
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
     * @param mixed $id
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function getItem(
        string $resourceClass,
        $id,
        ?string $operationName = null,
        array $context = []
    ) {
        /** @var Message $message */
        $message = $this->denormalizer->denormalize($id, $resourceClass, null, $context);

        return $this->eventEngine->dispatch($message);
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []): bool
    {
        return $this->denormalizer->supportsDenormalization([], $resourceClass, null);
    }
}
