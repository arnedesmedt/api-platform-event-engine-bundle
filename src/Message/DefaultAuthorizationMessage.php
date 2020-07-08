<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

trait DefaultAuthorizationMessage
{
    /**
     * @inheritDoc
     */
    public static function __authorizationAttributes(): array
    {
        return [];
    }
}
