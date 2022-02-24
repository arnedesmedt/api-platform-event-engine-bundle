<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\Util\StringUtil;

trait TypeNameIsEntityName
{
    public static function __type(): string
    {
        return StringUtil::entityNameFromClassName(static::class);
    }
}
