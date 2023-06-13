<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

trait DefaultCacheMessageLogic
{
    public static function __maxAge(): int
    {
        return 5;
    }

    public static function __sharedMaxAge(): int
    {
        return 120;
    }

    /** @return array<string> */
    public static function __vary(): array
    {
        return ['Authorization', 'Accept-Language'];
    }

}
