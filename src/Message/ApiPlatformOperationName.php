<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use function preg_match;

trait ApiPlatformOperationName
{
    public static function operationName() : ?string
    {
        switch (true) {
            case preg_match('/(Create|Add)/', self::shortName()):
                return Name::POST;
            case preg_match('/(Get)/', self::shortName()):
                return Name::GET;
            case preg_match('/(Update)/', self::shortName()):
                return Name::PUT;
            case preg_match('/(Change)/', self::shortName()):
                return Name::PATCH;
            case preg_match('/(Delete|Remove)/', self::shortName()):
                return Name::DELETE;
        }

        return null;
    }
}
