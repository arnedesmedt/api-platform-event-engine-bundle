<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Type;
use function preg_match;

trait ApiPlatformOperationType
{
    public static function operationType() : ?string
    {
        switch (true) {
            case preg_match('/(Create|Add|GetAll)/', self::shortName()):
                return Type::COLLECTION;
            case preg_match('/(Update|Get|Change|Delete|Remove)/', self::shortName()):
                return Type::ITEM;
        }

        return null;
    }
}
