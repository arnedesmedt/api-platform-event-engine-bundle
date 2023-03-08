<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use EventEngine\Schema\TypeSchema;

interface AuthorizationMessage
{
    /** @return array<string> */
    public static function __authorizationAttributes(): array;

    /** @return array<int, TypeSchema> */
    public static function __extraResponseClassesAuthorization(): array;
}
