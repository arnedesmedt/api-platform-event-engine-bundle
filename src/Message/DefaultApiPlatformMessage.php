<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Type\DefaultType;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Action\PlaceholderAction;
use ApiPlatform\Core\Api\OperationType;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function class_exists;
use function lcfirst;
use function method_exists;
use function preg_match;
use function sprintf;
use function ucfirst;

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

        $entityNamespace = StringUtil::entityNamespaceFromClassName(static::class);
        $entityName = StringUtil::entityNameFromClassName(static::class);
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

        return match (true) {
            (bool) preg_match(
                '/(Create|Add|GetAll|All|Enable|Import)/',
                $shortName
            ) => OperationType::COLLECTION,
            (bool) preg_match(
                '/(Update|Get|Change|Delete|Remove|ByUuid|ById|Disable)/',
                $shortName
            ) => OperationType::ITEM,
            default => throw ApiPlatformMappingException::noOperationTypeFound(static::class),
        };
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

        return match (true) {
            (bool) preg_match('/(Create|Add|Enable|Import)/', $shortName) => Name::POST,
            (bool) preg_match('/(Get|GetAll|All|ById|ByUuid)/', $shortName) => Name::GET,
            (bool) preg_match('/(Update)/', $shortName) => Name::PUT,
            (bool) preg_match('/(Change)/', $shortName) => Name::PATCH,
            (bool) preg_match('/(Delete|Remove|Disable)/', $shortName) => Name::DELETE,
            default => throw ApiPlatformMappingException::noOperationNameFound(static::class),
        };
    }

    public static function __operationId(): string
    {
        return lcfirst(self::__operationName())
            . ucfirst(StringUtil::entityNameFromClassName(static::class))
            . ucfirst(self::__operationType());
    }

    public static function __httpMethod(): ?string
    {
        return match (self::__operationName()) {
            Name::POST => Request::METHOD_POST,
            Name::DELETE => Request::METHOD_DELETE,
            Name::PUT => Request::METHOD_PUT,
            Name::PATCH => Request::METHOD_PATCH,
            Name::GET => Request::METHOD_GET,
            default => null,
        };
    }

    public static function __path(): ?string
    {
        return null;
    }

    public static function __pathUri(): ?Uri
    {
        $path = static::__path();
        if ($path === null) {
            return null;
        }

        return Uri::fromString($path);
    }

    public static function __apiPlatformController(): string
    {
        return PlaceholderAction::class;
    }

    public static function __stateless(): ?bool
    {
        return null;
    }

    public static function __schemaStateClass(): string
    {
        return static::__entity();
    }

    /**
     * @inheritDoc
     */
    public static function __tags(): array
    {
        $tags = [StringUtil::entityNameFromClassName(static::class)];

        if (method_exists(self::class, '__rootResourceClass')) {
            $tags[] = StringUtil::entityNameFromClassName(self::__rootResourceClass());
        }

        return $tags;
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

        $responses[Response::HTTP_CREATED] = match (self::__httpMethod()) {
            Request::METHOD_POST => DefaultType::created(),
            Request::METHOD_DELETE => DefaultType::emptyResponse(),
            Request::METHOD_PUT, Request::METHOD_PATCH,
            Request::METHOD_GET, Request::METHOD_OPTIONS => DefaultType::ok(),
            default => $responses,
        };

        return $responses;
    }

    public static function __inputClass(): ?string
    {
        return null;
    }

    public static function __outputClass(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public static function __normalizationContext(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function __denormalizationContext(): array
    {
        return [];
    }

    public static function __overrideDefaultApiPlatformResponse(): bool
    {
        return false;
    }

    private static function shortName(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return $reflectionClass->getShortName();
    }
}
