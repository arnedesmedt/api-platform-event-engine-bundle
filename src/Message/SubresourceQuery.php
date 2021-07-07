<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\EventEngineBundle\Query\Query;

interface SubresourceQuery extends Query
{
    public static function __rootResourceClass(): string;

    public static function __subresourceIsCollection(): bool;
}
