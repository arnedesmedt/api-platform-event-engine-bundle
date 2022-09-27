<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Created;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Deleted;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Ok;
use ADS\Util\StringUtil;
use ApiPlatform\Action\PlaceholderAction;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function class_exists;
use function lcfirst;
use function method_exists;
use function preg_match;
use function sprintf;
use function ucfirst;

/**
 * @method static string __uriTemplate()
 * @method static string|null __customProcessor()
 */
trait DefaultApiPlatformMessage
{
    public static function __resource(): string
    {
        $resourceNamespace = StringUtil::entityNamespaceFromClassName(static::class);
        $resourceName = StringUtil::entityNameFromClassName(static::class);
        $resourceStateClass = sprintf('%s\\%s\\%s', $resourceNamespace, $resourceName, 'State');

        if (! class_exists($resourceStateClass)) {
            throw ApiPlatformMappingException::noResourceFound(static::class);
        }

        return $resourceStateClass;
    }

    public static function __isCollection(): bool
    {
        $shortName = static::shortName();

        return (bool) preg_match(
            '/(Create|Add|GetAll|All|Enable|Import)/',
            $shortName
        );
    }

    public static function __operationName(): string
    {
        if (method_exists(static::class, '__customOperationName')) {
            $customOperationName = static::__customOperationName();

            if ($customOperationName !== null) {
                return $customOperationName;
            }
        }

        $shortName = static::shortName();

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
        return lcfirst(static::__operationName()) .
            ucfirst(StringUtil::entityNameFromClassName(static::class)) .
            (static::__isCollection() ? 'Collection' : 'Item');
    }

    public static function __httpMethod(): string
    {
        return match (static::__operationName()) {
            Name::POST => Request::METHOD_POST,
            Name::POST . 'Deprecated' => Request::METHOD_POST,
            Name::DELETE => Request::METHOD_DELETE,
            Name::DELETE . 'Deprecated' => Request::METHOD_DELETE,
            Name::PUT => Request::METHOD_PUT,
            Name::PUT . 'Deprecated' => Request::METHOD_PUT,
            Name::PATCH => Request::METHOD_PATCH,
            Name::PATCH . 'Deprecated' => Request::METHOD_PATCH,
            Name::GET => Request::METHOD_GET,
            Name::GET . 'Deprecated' => Request::METHOD_GET,
            default => throw new RuntimeException(
                sprintf(
                    'No __httpMethod method found in class \'%s\'.',
                    static::class
                )
            ),
        };
    }

    public static function __apiPlatformController(): string
    {
        return PlaceholderAction::class;
    }

    public static function __processor(): ?string
    {
        return method_exists(static::class, '__customProcessor')
            ? static::__customProcessor()
            : null;
    }

    public static function __stateless(): ?bool
    {
        return null;
    }

    public static function __schemaStateClass(): string
    {
        return static::__resource();
    }

    public static function __schemaStatesClass(): string
    {
        return static::__schemaStateClass() . 's';
    }

    /**
     * @inheritDoc
     */
    public static function __tags(): array
    {
        return [StringUtil::entityNameFromClassName(static::class)];
    }

    public static function __requestBodyArrayProperty(): ?string
    {
        return null;
    }

    /**
     * @return array<int, class-string>
     */
    public static function __extraResponseClassesApiPlatform(): array
    {
        $responses = [];

        $value = match (self::__httpMethod()) {
            Request::METHOD_POST => Created::class,
            Request::METHOD_DELETE => Deleted::class,
            Request::METHOD_PUT, Request::METHOD_PATCH => Ok::class,
            Request::METHOD_GET, Request::METHOD_OPTIONS => static::__isCollection()
                ? static::__schemaStatesClass()
                : static::__schemaStateClass(),
            default => null,
        };

        $key = match (self::__httpMethod()) {
            Request::METHOD_POST => Response::HTTP_CREATED,
            Request::METHOD_DELETE => Response::HTTP_NO_CONTENT,
            Request::METHOD_PUT, Request::METHOD_PATCH,
            Request::METHOD_GET, Request::METHOD_OPTIONS => Response::HTTP_OK,
            default => null,
        };

        if ($key !== null && $value !== null) {
            $responses[$key] = $value;
        }

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
