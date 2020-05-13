<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMapping;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Type;
use ReflectionClass;
use function array_pop;
use function count;
use function explode;
use function implode;
use function method_exists;
use function preg_match;
use function sprintf;

/**
 * @method static string|null __customOperationName()
 */
trait DefaultApiPlatformMessage
{
    public static function __entity() : string
    {
        $parts = explode('\\', static::class);
        array_pop($parts);
        array_pop($parts);
        $namespace = implode('\\', $parts);

        return sprintf('%s\\%s', $namespace, $parts[count($parts) - 1]);
    }

    public static function __operationType() : string
    {
        $shortName = self::shortName();

        switch (true) {
            case preg_match('/(Create|Add|GetAll|All)/', $shortName):
                return Type::COLLECTION;
            case preg_match('/(Update|Get|Change|Delete|Remove|ById)/', $shortName):
                return Type::ITEM;
        }

        throw ApiPlatformMapping::noOperationTypeFound(static::class);
    }

    public static function __operationName() : string
    {
        $shortName = self::shortName();

        switch (true) {
            case preg_match('/(Create|Add)/', $shortName):
                return Name::POST;
            case preg_match('/(Get|GetAll|All|ById)/', $shortName):
                return Name::GET;
            case preg_match('/(Update)/', $shortName):
                return Name::PUT;
            case preg_match('/(Change)/', $shortName):
                return Name::PATCH;
            case preg_match('/(Delete|Remove)/', $shortName):
                return Name::DELETE;
        }

        if (method_exists(static::class, '__customOperationName')) {
            $customOperationName = static::__customOperationName();

            if ($customOperationName !== null) {
                return $customOperationName;
            }
        }

        throw ApiPlatformMapping::noOperationNameFound(static::class);
    }

    private static function shortName() : string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return $reflectionClass->getShortName();
    }
}
