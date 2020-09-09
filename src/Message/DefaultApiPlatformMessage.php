<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ApiPlatform\Core\Api\OperationType;
use ReflectionClass;

use function array_pop;
use function class_exists;
use function explode;
use function implode;
use function method_exists;
use function preg_match;
use function sprintf;

trait DefaultApiPlatformMessage
{
    public static function __entity(): string
    {
        if (method_exists(static::class, '__customEntity')) {
            $customEntity = static::__customEntity();

            if ($customEntity !== null) {
                return $customEntity;
            }
        }

        $parts = explode('\\', static::class);
        array_pop($parts);
        array_pop($parts);
        $namespace = implode('\\', $parts);

        $entityClass = sprintf('%s\\%s', $namespace, 'State');

        if (! class_exists($entityClass)) {
            throw ApiPlatformMappingException::noEntityFound(static::class);
        }

        return $entityClass;
    }

    public static function __operationType(): string
    {
        if (method_exists(static::class, '__customOperationType')) {
            $customOperationType = static::__customOperationType();

            if ($customOperationType !== null) {
                return $customOperationType;
            }
        }

        $shortName = self::shortName();

        switch (true) {
            case preg_match('/(Create|Add|GetAll|All|Enable)/', $shortName):
                return OperationType::COLLECTION;

            case preg_match('/(Update|Get|Change|Delete|Remove|ByUuid|ById|Disable)/', $shortName):
                return OperationType::ITEM;
        }

        throw ApiPlatformMappingException::noOperationTypeFound(static::class);
    }

    public static function __operationName(): string
    {
        if (method_exists(static::class, '__customOperationName')) {
            $customOperationName = static::__customOperationName();

            if ($customOperationName !== null) {
                return $customOperationName;
            }
        }

        $shortName = self::shortName();

        switch (true) {
            case preg_match('/(Create|Add|Enable)/', $shortName):
                return Name::POST;

            case preg_match('/(Get|GetAll|All|ById|ByUuid)/', $shortName):
                return Name::GET;

            case preg_match('/(Update)/', $shortName):
                return Name::PUT;

            case preg_match('/(Change)/', $shortName):
                return Name::PATCH;

            case preg_match('/(Delete|Remove|Disable)/', $shortName):
                return Name::DELETE;
        }

        throw ApiPlatformMappingException::noOperationNameFound(static::class);
    }

    /**
     * @inheritDoc
     */
    public static function __examples(): ?array
    {
        return null;
    }

    private static function shortName(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return $reflectionClass->getShortName();
    }
}
