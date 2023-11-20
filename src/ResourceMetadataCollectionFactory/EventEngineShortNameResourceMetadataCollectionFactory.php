<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;

final class EventEngineShortNameResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    use EventEngineResourceMetdataCollectionFactoryLogic;

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface|null $decorated = null,
    ) {
    }

    protected function decorateEventEngineResource(EventEngineResource $eventEngineResource): ApiResource
    {
        return $eventEngineResource->withShortName($this->resourceClass::__type());
    }
}
