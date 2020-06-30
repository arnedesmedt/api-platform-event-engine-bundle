<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use function lcfirst;

/**
 * @method static string shortName()
 */
trait OperationNameIsMessageName
{
    public static function __customOperationName(): ?string
    {
        return lcfirst(static::shortName());
    }
}
