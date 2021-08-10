<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Type;

use ADS\JsonImmutableObjects\DefaultsAreNotRequired;
use ADS\Util\StringUtil;

trait TypeNameIsEntityName
{
    use DefaultsAreNotRequired;

    public static function __type(): string
    {
        return StringUtil::entityNameFromClassName(static::class);
    }
}
