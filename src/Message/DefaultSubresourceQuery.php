<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\EventEngineBundle\Query\DefaultQuery;

trait DefaultSubresourceQuery
{
    use DefaultQuery;

    public static function __subresourceIsCollection(): bool
    {
        return true;
    }
}
