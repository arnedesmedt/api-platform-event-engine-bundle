<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;
use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use ReflectionClass;

trait TypeNameIsEntityNameAndClassName
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return Util::entityNameFromClassName(static::class) . $reflectionClass->getShortName();
    }
}
