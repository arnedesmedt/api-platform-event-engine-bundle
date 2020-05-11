<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource;

use ADS\Bundle\EventEngineBundle\Util;

trait DefaultApiResourceState
{
    public static function __newApiResource() : string
    {
        return Util::fromStateToAggregateClass(static::class);
    }
}
