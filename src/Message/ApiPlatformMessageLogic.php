<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Type;
use ReflectionClass;

trait ApiPlatformMessageLogic
{
    public static function operationType() : ?string
    {
        switch (self::shortName()) {
            case 'Create':
            case 'Add':
            case 'GetAll':
                return Type::COLLECTION;
            case 'Update':
            case 'Get':
            case 'Change':
            case 'Delete':
            case 'Remove':
                return Type::ITEM;
        }

        return null;
    }

    public static function operationName() : ?string
    {
        switch (self::shortName()) {
            case 'Create':
            case 'Add':
                return Name::POST;
            case 'GetAll':
            case 'Get':
                return Name::GET;
            case 'Update':
                return Name::PUT;
            case 'Change':
                return Name::PATCH;
            case 'Delete':
            case 'Remove':
                return Name::DELETE;
        }

        return null;
    }

    private static function shortName() : string
    {
        $reflectionClass = new ReflectionClass(self::class);

        return $reflectionClass->getShortName();
    }
}
