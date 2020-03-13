<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ReflectionClass;

trait ApiPlatformMessageLogic
{
    private static function shortName() : string
    {
        $reflectionClass = new ReflectionClass(self::class);

        return $reflectionClass->getShortName();
    }
}
