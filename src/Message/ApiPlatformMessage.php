<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

interface ApiPlatformMessage
{
    /**
     * @return class-string
     */
    public static function __entity(): string;

    public static function __operationType(): string;

    public static function __operationName(): string;

    public static function __requestBodyArrayProperty(): ?string;
}
