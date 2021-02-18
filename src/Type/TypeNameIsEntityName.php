<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;

trait TypeNameIsEntityName
{
    public static function __typeName(): string
    {
        return Util::entityNameFromClassName(static::class);
    }
}
