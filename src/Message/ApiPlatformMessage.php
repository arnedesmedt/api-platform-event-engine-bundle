<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

interface ApiPlatformMessage
{
    public static function operationType() : ?string;

    public static function operationName() : ?string;
}
