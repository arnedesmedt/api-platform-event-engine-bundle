<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ReflectionClass;

trait ApiPlatformMessageLogic
{
    public static function operationType() : ?string
    {
        switch (self::shortName()) {
            case 'Create':
            case 'Add':
            case 'GetAll':
                return 'collection';
            case 'Update':
            case 'Get':
            case 'Change':
            case 'Delete':
            case 'Remove':
                return 'item';
        }

        return null;
    }

    public static function operationName() : ?string
    {
        switch (self::shortName()) {
            case 'Create':
            case 'Add':
                return 'post';
            case 'GetAll':
            case 'Get':
                return 'get';
            case 'Update':
                return 'put';
            case 'Change':
                return 'patch';
            case 'Delete':
            case 'Remove':
                return 'delete';
        }

        return null;
    }

    private static function shortName() : string
    {
        $reflectionClass = new ReflectionClass(self::class);

        return $reflectionClass->getShortName();
    }
}
