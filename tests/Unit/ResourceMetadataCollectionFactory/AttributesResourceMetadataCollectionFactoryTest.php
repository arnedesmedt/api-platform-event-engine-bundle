<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Unit\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Resource\TestResource;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use PHPUnit\Framework\TestCase;

class AttributesResourceMetadataCollectionFactoryTest extends TestCase
{
    private AttributesResourceMetadataCollectionFactory $resourceMetadataCollectionFactory;

    protected function setUp(): void
    {
        $this->resourceMetadataCollectionFactory = new AttributesResourceMetadataCollectionFactory();
    }

    public function testTestResource(): void
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create(TestResource::class);

        $this->assertCount(1, $resourceMetadataCollection);

        $apiResource = $resourceMetadataCollection[0];
        $this->assertInstanceOf(EventEngineResource::class, $apiResource);
        $this->assertEquals('TestResource', $apiResource->getShortName());
        $this->assertNotNull($apiResource->getOperations());
        $this->assertCount(0, $apiResource->getOperations());
    }
}