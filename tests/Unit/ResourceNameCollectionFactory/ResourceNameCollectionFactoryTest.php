<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Unit\ResourceNameCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Resource\FakeTestResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Resource\TestResource;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceNameCollectionFactory;
use PHPUnit\Framework\TestCase;

class ResourceNameCollectionFactoryTest extends TestCase
{
    private AttributesResourceNameCollectionFactory $resourceNameCollectionFactory;

    protected function setUp(): void
    {
        $this->resourceNameCollectionFactory = new AttributesResourceNameCollectionFactory(
            [__DIR__ . '/../../Object/Resource/'],
        );
    }

    public function testResourceNameCollection(): void
    {
        $resourceNameCollectionFactory = $this->resourceNameCollectionFactory->create();

        $this->assertCount(1, $resourceNameCollectionFactory);
        $this->assertContains(TestResource::class, $resourceNameCollectionFactory);
        $this->assertNotContains(FakeTestResource::class, $resourceNameCollectionFactory);
    }
}