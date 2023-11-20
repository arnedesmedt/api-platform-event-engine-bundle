<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

/**
 * @property ResourceMetadataCollectionFactoryInterface $decorated
 */
trait EventEngineResourceMetdataCollectionFactoryLogic
{
    /** @var class-string<JsonSchemaAwareRecord> */
    private string $resourceClass;

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $this->resourceClass = $resourceClass;

        $resourceMetadataCollection = $this->decorated
            ? $this->decorated->create($resourceClass)
            : new ResourceMetadataCollection($resourceClass);

        if (! is_a($resourceClass, JsonSchemaAwareRecord::class, true)) {
            return $resourceMetadataCollection;
        }

        foreach ($resourceMetadataCollection as &$eventEngineResource) {
            if (! $eventEngineResource instanceof EventEngineResource) {
                continue;
            }

            $eventEngineResource = $this->decorateEventEngineResource($eventEngineResource);
        }

        return $resourceMetadataCollection;
    }

    abstract protected function decorateEventEngineResource(
        EventEngineResource $eventEngineResource,
    ): ApiResource;
}