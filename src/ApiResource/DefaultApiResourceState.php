<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource;

use ADS\Bundle\EventEngineBundle\Util\EventEngineUtil;

trait DefaultApiResourceState
{
    public static function __newApiResource() : string
    {
        return EventEngineUtil::fromStateToAggregateClass(static::class);
    }
}
