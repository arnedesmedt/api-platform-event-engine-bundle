<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use ADS\Util\StringUtil;

trait TypeNameIsEntityName
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        return StringUtil::entityNameFromClassName(static::class);
    }
}
