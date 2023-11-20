<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Unit\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory\EventEngineOperationsResourceMetadataCollectionFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory\EventEngineShortNameResourceMetadataCollectionFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Resource\TestResource;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class EventEngineOperationsResourceMetadataCollectionFactoryTest extends TestCase
{
    private EventEngineOperationsResourceMetadataCollectionFactory $resourceMetadataCollectionFactory;

    protected function setUp(): void
    {
        $this->resourceMetadataCollectionFactory = new EventEngineOperationsResourceMetadataCollectionFactory(
            new Finder(),
            new AttributesResourceMetadataCollectionFactory(),
        );
    }

    public function testTestResource(): void
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create(TestResource::class);
    }
}