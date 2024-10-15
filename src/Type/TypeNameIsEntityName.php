<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use TeamBlue\Util\StringUtil;

trait TypeNameIsEntityName
{
    use JsonSchemaAwareRecordLogic;

    public static function __type(): string
    {
        return StringUtil::entityNameFromClassName(static::class);
    }
}
