<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use function lcfirst;

trait ApiPlatformOperationNameIsMessageName
{
    public static function operationName() : ?string
    {
        return lcfirst(self::shortName());
    }
}
