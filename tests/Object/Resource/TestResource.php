<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Resource;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ADS\Bundle\EventEngineBundle\Type\Type;
use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

#[EventEngineResource(
    commandFolders: [__DIR__ . '/../Command'],
)]
class TestResource implements Type
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        return 'TestResourceAlias';
    }
}