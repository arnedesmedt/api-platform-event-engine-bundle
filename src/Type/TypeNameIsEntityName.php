<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;
use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

trait TypeNameIsEntityName
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        return Util::entityNameFromClassName(static::class);
    }
}
