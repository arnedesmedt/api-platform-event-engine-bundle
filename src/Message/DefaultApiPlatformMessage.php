<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Type\DefaultType;
use ApiPlatform\Core\Action\PlaceholderAction;
use ApiPlatform\Core\Api\OperationType;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_diff;
use function array_keys;
use function class_exists;
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
                if (! class_exists($customEntity)) {
                    throw ApiPlatformMappingException::noEntityFound(static::class);
                }

                return $customEntity;
            }
        }

        $entityNamespace = Util::entityNamespaceFromClassName(static::class);
        $entityName = Util::entityNameFromClassName(static::class);
        $entityStateClass = sprintf('%s\\%s\\%s', $entityNamespace, $entityName, 'State');

        if (! class_exists($entityStateClass)) {
            throw ApiPlatformMappingException::noEntityFound(static::class);
        }

        return $entityStateClass;
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

    public static function __httpMethod(): ?string
    {
        switch (self::__operationName()) {
            case Name::POST:
                return Request::METHOD_POST;

            case Name::DELETE:
                return Request::METHOD_DELETE;

            case Name::PUT:
                return Request::METHOD_PUT;

            case Name::PATCH:
                return Request::METHOD_PATCH;

            case Name::GET:
                return Request::METHOD_GET;
        }

        return null;
    }

    public static function __path(): ?string
    {
        return null;
    }

    public static function __apiPlatformController(): string
    {
        return PlaceholderAction::class;
    }

    /**
     * @inheritDoc
     */
    public static function __tags(): array
    {
        return [Util::entityNameFromClassName(static::class)];
    }

    public static function __requestBodyArrayProperty(): ?string
    {
        return null;
    }

    /**
     * @return array<int, TypeSchema>
     */
    public static function __extraResponseApiPlatform(): array
    {
        $responses = [];
        $path = self::__path();

        if ($path !== null && method_exists(self::class, 'buildPropTypeMap')) {
            $pathUri = Uri::fromString($path);
            $parameterNames = $pathUri->toAllParameterNames();
            $messageParameterNames = array_keys(self::buildPropTypeMap());

            if (! empty(array_diff($messageParameterNames, $parameterNames))) {
                $responses[Response::HTTP_BAD_REQUEST] = ApiPlatformException::badRequest();
            }
        }

        switch (self::__httpMethod()) {
            case Request::METHOD_POST:
                $responses[Response::HTTP_CREATED] = DefaultType::created();
                break;
            case Request::METHOD_DELETE:
                $responses[Response::HTTP_NO_CONTENT] = DefaultType::emptyResponse();
                break;
            case Request::METHOD_PUT:
            case Request::METHOD_PATCH:
            case Request::METHOD_GET:
            case Request::METHOD_OPTIONS:
                $responses[Response::HTTP_OK] = DefaultType::ok();
                break;
        }

        return $responses;
    }

    private static function shortName(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return $reflectionClass->getShortName();
    }
}
