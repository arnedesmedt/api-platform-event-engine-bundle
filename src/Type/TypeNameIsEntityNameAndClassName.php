<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ReflectionClass;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use TeamBlue\Util\StringUtil;

trait TypeNameIsEntityNameAndClassName
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return StringUtil::entityNameFromClassName(static::class) . $reflectionClass->getShortName();
    }
}
