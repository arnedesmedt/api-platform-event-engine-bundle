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

    /**
     * @return array<string, mixed>|null
     */
    public static function __examples(): ?array;
}
