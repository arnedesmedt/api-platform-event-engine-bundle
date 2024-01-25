<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

interface CacheMessage
{
    public static function __maxAge(): int;

    public static function __sharedMaxAge(): int;

    /** @return array<string> */
    public static function __vary(): array;
}
