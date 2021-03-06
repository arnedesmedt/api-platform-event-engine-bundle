<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Type\DefaultType;
use ADS\Util\ArrayUtil;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Action\PlaceholderAction;
use ApiPlatform\Core\Api\OperationType;
use EventEngine\JsonSchema\Type\ObjectType;
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

        switch (true) {
            case preg_match('/(Create|Add|GetAll|All|Enable|Import)/', $shortName):
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
            case preg_match('/(Create|Add|Enable|Import)/', $shortName):
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

    public static function __operationId(): string
    {
        return lcfirst(self::__operationName())
            . ucfirst(StringUtil::entityNameFromClassName(static::class))
            . ucfirst(self::__operationType());
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

    public static function __pathUri(): ?Uri
    {
        $path = static::__path();
        if ($path === null) {
            return null;
        }

        return Uri::fromString($path);
    }

    public function replacedPathUri(?Uri $pathUri = null): ?Uri
    {
        $pathUri ??= self::__pathUri();

        if ($pathUri === null) {
            return null;
        }

        return $pathUri->replaceAllParameters($this->toArray());
    }

    public static function __apiPlatformController(): string
    {
        return PlaceholderAction::class;
    }

    public static function __stateless(): ?bool
    {
        return null;
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

    /**
     * @return array<mixed>|null
     */
    public static function __pathSchema(?Uri $pathUri = null): ?array
    {
        return self::filterSchema($pathUri);
    }

    /**
     * @return array<mixed>|null
     */
    public static function __requestBodySchema(?Uri $pathUri = null): ?array
    {
        $schema = self::filterSchema($pathUri, 'removeParameters');

        if (
            $schema
            && self::__requestBodyArrayProperty()
        ) {
            $schema = $schema['properties'][self::__requestBodyArrayProperty()];
        }

        return $schema;
    }

    /**
     * @return array<mixed>|null
     */
    private static function filterSchema(?Uri $pathUri = null, string $method = 'filterParameters'): ?array
    {
        $pathUri ??= static::__pathUri();
        $schema = static::__schema();

        if ($pathUri === null || ! $schema instanceof ObjectType) {
            return null;
        }

        $parameterNames = $pathUri->toAllParameterNames();
        $schema = OpenApiSchemaFactory::toOpenApiSchema($schema->toArray());

        return MessageSchemaFactory::$method($schema, $parameterNames);
    }

    /**
     * @inheritDoc
     */
    public function toPathArray(?Uri $pathUri = null): ?array
    {
        return $this->filterToArray($pathUri);
    }

    /**
     * @inheritDoc
     */
    public function toRequestBodyArray(?Uri $pathUri = null): ?array
    {
        $data = $this->filterToArray($pathUri, 'diff');

        if (
            $data
            && self::__requestBodyArrayProperty()
        ) {
            $data = $data[self::__requestBodyArrayProperty()];
        }

        return $data;
    }

    /**
     * @return array<mixed>|null
     */
    private function filterToArray(?Uri $pathUri = null, string $method = 'intersect'): ?array
    {
        $pathUri ??= static::__pathUri();

        if ($pathUri === null) {
            return null;
        }

        $parameterNames = ArrayUtil::toCamelCasedValues($pathUri->toAllParameterNames());
        $data = $this->toArray();
        $method = sprintf('array_%s_key', $method);

        return $method($data, $parameterNames);
    }

    private static function shortName(): string
    {
        $reflectionClass = new ReflectionClass(static::class);

        return $reflectionClass->getShortName();
    }
}
