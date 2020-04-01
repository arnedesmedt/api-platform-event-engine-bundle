<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource\ChangeApiResource;
use ADS\Bundle\EventEngineBundle\Config;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;
use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class DocumentStoreItemDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private DenormalizerInterface $denormalizer;
    private EventEngine $eventEngine;
    private Config $eventEngineConfig;

    public function __construct(
        DenormalizerInterface $denormalizer,
        EventEngine $eventEngine,
        Config $eventEngineConfig
    ) {
        $this->denormalizer = $denormalizer;
        $this->eventEngine = $eventEngine;
        $this->eventEngineConfig = $eventEngineConfig;
    }

    /**
     * @param class-string $resourceClass
     * @param mixed $id
     * @param array<mixed> $context
     */
    public function getItem(
        string $resourceClass,
        $id,
        ?string $operationName = null,
        array $context = []
    ) : ?ImmutableRecord {
        $reflectionClass = new ReflectionClass($resourceClass);

        if ($reflectionClass->implementsInterface(ChangeApiResource::class)) {
            $resourceClass = $resourceClass::__newApiResource();
        }

        /** @var string $identifier */
        $identifier = $this->eventEngineConfig->aggregateIdentifiers($resourceClass);

        /** @var Message $message */
        $message = $this->denormalizer->denormalize([$identifier => $id], $resourceClass, null, $context);

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
