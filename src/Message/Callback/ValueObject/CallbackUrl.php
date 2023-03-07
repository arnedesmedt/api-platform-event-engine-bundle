<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject;

use ADS\ValueObjects\Implementation\String\UrlValue;

final class CallbackUrl extends UrlValue
{
    public static function example(): static
    {
        return new static($_SERVER['CALLBACK_URL'] ?? 'https://webhook.site');
    }
}
