<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use ADS\Util\StringUtil;
use ReflectionClass;

trait TypeNameIsEntityNameAndClassName
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return StringUtil::entityNameFromClassName(static::class) . $reflectionClass->getShortName();
    }
}
