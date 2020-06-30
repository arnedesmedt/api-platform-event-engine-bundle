<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource;

interface ChangeApiResource
{
    /**
     * @return class-string
     */
    public static function __newApiResource(): string;
}
