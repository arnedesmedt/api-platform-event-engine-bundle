<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

interface ApiPlatformMessage
{
    /**
     * @return class-string|null
     */
    public static function entity() : ?string;

    public static function operationType() : ?string;

    public static function operationName() : ?string;
}
