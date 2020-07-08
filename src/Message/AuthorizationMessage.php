<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

interface AuthorizationMessage
{
    /**
     * @return array<string>
     */
    public static function __authorizationAttributes(): array;
}
