<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CallbackMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_combine;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function in_array;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtoupper;
use function substr;
use function ucfirst;

use const ARRAY_FILTER_USE_BOTH;

final class MessageResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private const COMMAND_METHODS = [
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
    ];
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        private ResourceMetadataFactoryInterface $decorated,
        private Config $config,
        private OperationPathResolverInterface $operationPathResolver
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        $messageMapping = $this->config->messageMapping();

        if (! array_key_exists($resourceClass, $messageMapping)) {
            return $resourceMetadata;
        }

        $resourceMessageMapping = $messageMapping[$resourceClass];

        foreach (OperationType::TYPES as $operationType) {
            $getMethod = sprintf('get%sOperations', ucfirst($operationType));
            $withMethod = sprintf('with%sOperations', ucfirst($operationType));

            $operations = $resourceMetadata->{$getMethod}() ?? [];
            $operations = self::rejectSimpleOperations($operations);
            $messages = self::filterApiPlatformMessages($resourceMessageMapping[$operationType] ?? []);

            $operations = $this->addNoDefaultOperationNames(
                $operations,
                $messages
            );

            $operations = $this->decorateOperations(
                $resourceClass,
                $resourceMetadata,
                $operationType,
                $operations,
                $messages
            );

            $resourceMetadata = $resourceMetadata->{$withMethod}($operations);
        }

        return $resourceMetadata;
    }

    /**
     * Reject the simple api platform operations.
     * These are operations automatically created by api platform.
     *
     * @param array<string, array<mixed>> $operations
     *
     * @return array<string, array<mixed>>
     */
    private static function rejectSimpleOperations(array $operations): array
    {
        return array_filter(
            $operations,
            static fn ($operation, $operationName) => ! isset($operation['method'])
                || $operation['method'] !== strtoupper($operationName),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param array<string, class-string> $messageClasses
     *
     * @return array<string, class-string<ApiPlatformMessage>>
     */
    public static function filterApiPlatformMessages(array $messageClasses): array
    {
        /** @var array<string, class-string<ApiPlatformMessage>> $result */
        $result =  array_filter(
            $messageClasses,
            static fn ($messageClass) => (new ReflectionClass($messageClass))
                ->implementsInterface(ApiPlatformMessage::class)
        );

        return $result;
    }

    /**
     * Add the operations that don't have a default name like get or post.
     *
     * @param array<string, array<mixed>> $existingOperations
     * @param array<string, class-string<ApiPlatformMessage>> $messagesByOperationName
     *
     * @return array<string, array<mixed>>
     */
    private function addNoDefaultOperationNames(array $existingOperations, array $messagesByOperationName): array
    {
        $operationKeysToAdd = array_diff_key($messagesByOperationName, $existingOperations);
        $operationsToAdd = array_fill_keys(array_keys($operationKeysToAdd), []);

        return array_merge($existingOperations, $operationsToAdd);
    }

    /**
     * @param array<string, array<mixed>> $operations
     * @param array<string, class-string<ApiPlatformMessage>> $messagesByOperationName
     *
     * @return array<string, array<mixed>>
     */
    private function decorateOperations(
        string $resourceClass,
        ResourceMetadata $resourceMetadata,
        string $operationType,
        array $operations,
        array $messagesByOperationName
    ): array {
        $operationKeys = array_keys($operations);

        /** @var array<string, array<mixed>> $decoratedOperations */
        $decoratedOperations = array_combine(
            $operationKeys,
            array_map(
                function (
                    string $operationName,
                    array $operation
                ) use (
                    $messagesByOperationName,
                    $resourceMetadata,
                    $operationType,
                    $resourceClass
                ) {
                    $messageClass = $messagesByOperationName[$operationName] ?? false;

                    if ($messageClass) {
                        if (! isset($operation['openapi_context'])) {
                            $operation['openapi_context'] = [];
                        }

                        $reflectionClass = new ReflectionClass($messageClass);
                        $openApiContext = &$operation['openapi_context'];
                        try {
                            $docBlock = $this->docBlockFactory->create($reflectionClass);
                        } catch (InvalidArgumentException) {
                            $docBlock = null;
                        }

                        $this
                            ->addMessageClass($operation, $messageClass)
                            ->addOperationId($openApiContext, $messageClass)
                            ->addHttpMethod($operation, $messageClass)
                            ->addPath($operation, $operationType, $resourceMetadata, $messageClass)
                            ->addController($operation, $messageClass)
                            ->addRead($operation)
                            ->addStateless($operation, $messageClass)
                            ->addStatus($operation, $messageClass, $reflectionClass)
                            ->addInputClass($operation, $messageClass)
                            ->addOutputClass($operation, $messageClass)
                            ->addTags($openApiContext, $messageClass)
                            ->addDocumentation($openApiContext, $docBlock)
                            ->addCallbacks($openApiContext, $messageClass, $reflectionClass)
                            ->addDeprecated($operation, $docBlock)
                            ->addParameters($operation, $messageClass)
                            ->addExtensionProperties(
                                $operation,
                                $openApiContext,
                                $resourceClass,
                                $operationType,
                                $operationName
                            );
                    }

                    return $operation;
                },
                $operationKeys,
                $operations
            )
        );

        return $decoratedOperations;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addMessageClass(array &$operation, string $messageClass): self
    {
        $operation['message_class'] ??= $messageClass;

        return $this;
    }

    /**
     * @param array<mixed> $openApiContext
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addOperationId(array &$openApiContext, string $messageClass): self
    {
        $openApiContext['operationId'] ??= $messageClass::__operationId();

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addHttpMethod(array &$operation, string $messageClass): self
    {
        $operation['method'] ??= $messageClass::__httpMethod();

        if ($operation['method'] === null) {
            throw new RuntimeException(
                sprintf(
                    'No __httpMethod method found in class \'%s\'.',
                    $messageClass
                )
            );
        }

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addPath(
        array &$operation,
        string $operationType,
        ResourceMetadata $resourceMetadata,
        string $messageClass
    ): self {
        $operation['path'] ??= $messageClass::__path() ?? $this->path(
            $resourceMetadata,
            $operationType,
            $operation
        );

        return $this;
    }

    /**
     * @param array<mixed> $operation
     */
    private function path(
        ResourceMetadata $resourceMetadata,
        string $operationType,
        array $operation
    ): string {
        /** @var string $resourceShortName */
        $resourceShortName = $resourceMetadata->getShortName();

        $path = $this->operationPathResolver->resolveOperationPath($resourceShortName, $operation, $operationType);
        if (str_ends_with($path, '.{_format}')) {
            $path = substr($path, 0, -10);
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /**
     * @param array<mixed> $operation
     */
    private function addRead(array &$operation): self
    {
        $operation['read'] ??= ! in_array($operation['method'], self::COMMAND_METHODS);

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addController(array &$operation, string $messageClass): self
    {
        $operation['controller'] ??= $messageClass::__apiPlatformController();

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addStateless(array &$operation, string $messageClass): self
    {
        $operation['stateless'] ??= $messageClass::__stateless();

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addStatus(array &$operation, string $messageClass, ReflectionClass $reflectionClass): self
    {
        if (! $reflectionClass->implementsInterface(HasResponses::class)) {
            return $this;
        }

        $operation['status'] ??= $messageClass::__defaultStatusCode();

        return $this;
    }

    /**
     * @param array<string, array<string, mixed>> $operation
     */
    private function addInputClass(array &$operation, string $messageClass): self
    {
        $operation['input']['class'] ??= $messageClass::__inputClass();

        if ($operation['input']['class'] === null) {
            unset($operation['input']);
        }

        return $this;
    }

    /**
     * @param array<string, array<string, mixed>> $operation
     */
    private function addOutputClass(array &$operation, string $messageClass): self
    {
        $operation['output']['class'] ??= $messageClass::__outputClass();

        if ($operation['output']['class'] === null) {
            unset($operation['output']);
        }

        return $this;
    }

    /**
     * @param array<mixed> $openApiContext
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addTags(array &$openApiContext, string $messageClass): self
    {
        $openApiContext['tags'] ??= $messageClass::__tags();

        return $this;
    }

    /**
     * @param array<mixed> $openApiContext
     */
    private function addDocumentation(
        array &$openApiContext,
        ?DocBlock $docBlock = null
    ): self {
        if ($docBlock === null) {
            return $this;
        }

        $openApiContext['summary'] ??= $docBlock->getSummary();
        $openApiContext['description'] ??= $docBlock->getDescription()->render();

        return $this;
    }

    /**
     * @param array<mixed> $openApiContext
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addCallbacks(
        array &$openApiContext,
        string $messageClass,
        ReflectionClass $reflectionClass
    ): self {
        if (! $reflectionClass->implementsInterface(CallbackMessage::class)) {
            return $this;
        }

        $openApiContext['callbacks'] ??= $this->buildCallbacks($messageClass);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCallbacks(string $messageClass): array
    {
        /** @var array<string, class-string<JsonSchemaAwareRecord>> $events */
        $events = $messageClass::__callbackEvents();

        return array_map(
            static function (string $schemaClass) {
                return [
                    '{$request.body#/callbackUrl}' => [
                        'post' => [
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    'application/json' => [
                                        'schema' => OpenApiSchemaFactory::toOpenApiSchema(
                                            $schemaClass::__schema()->toArray()
                                        ),
                                    ],
                                ],
                            ],
                            'responses' => [
                                '200' => ['description' => 'Your server return a 200 OK, if it accpets the callback.'],
                            ],
                        ],
                    ],
                ];
            },
            $events
        );
    }

    /**
     * @param array<mixed> $operation
     */
    private function addDeprecated(
        array &$operation,
        ?DocBlock $docBlock = null
    ): self {
        if ($docBlock === null) {
            return $this;
        }

        $tags = $docBlock->getTagsByName('deprecated');

        if (empty($tags)) {
            return $this;
        }

        /** @var DocBlock\Tags\Deprecated $deprecatedTag */
        $deprecatedTag = $tags[0];
        $description = $deprecatedTag->getDescription();
        $operation['deprecation_reason'] = $description ? $description->render() : 'deprecated';

        return $this;
    }

    /**
     * @param array<string, array<string, mixed>> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addParameters(
        array &$operation,
        string $messageClass
    ): self {
        $operation['openapi_context']['parameters'] ??= [];

        /** @var string $path */
        $path = $operation['path'];
        $pathUri = $messageClass::__pathUri() ?? ($path ? Uri::fromString($path) : null);

        if ($pathUri === null) {
            return $this;
        }

        $schema = $messageClass::__schema()->toArray();

        $allParameterNames = $pathUri->toAllParameterNames();
        $pathParameterNames = $pathUri->toPathParameterNames();

        /** @var array<string, array<string, mixed>>|null $pathSchema */
        $pathSchema = MessageSchemaFactory::filterParameters($schema, $allParameterNames);

        if ($pathSchema === null && ! empty($allParameterNames)) {
            throw new RuntimeException(
                sprintf(
                    'The uri parameter names are not present in the message schema for message \'%s\'.',
                    $messageClass
                )
            );
        }

        if ($pathSchema === null) {
            return $this;
        }

        foreach ($allParameterNames as $parameterName) {
            /** @var array<string, mixed> $propertySchema */
            $propertySchema = $pathSchema['properties'][$parameterName];
            $in = in_array($parameterName, $pathParameterNames) ? 'path' : 'query';
            $name = StringUtil::decamelize($parameterName);

            if ($in === 'path' && isset($propertySchema['pattern'])) {
                $operation['requirements'][$name] = $propertySchema['pattern'];
            }

            $openApiSchema = OpenApiSchemaFactory::toOpenApiSchema($propertySchema);
            /* @phpstan-ignore-next-line */
            $operation['openapi_context']['parameters'][] = [
                'name' => $name,
                'in' => $in,
                'schema' => $openApiSchema,
                'required' => in_array($parameterName, $pathSchema['required']),
                'description' => $openApiSchema['description'] ?? self::typeDescription(
                    $messageClass,
                    $parameterName,
                    $this->docBlockFactory
                ),
                'deprecated' => $openApiSchema['deprecated'] ?? false,
                'example' => $openApiSchema['example'] ?? null,
            ];
        }

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param array<mixed> $openApiContext
     */
    private function addExtensionProperties(
        array &$operation,
        array &$openApiContext,
        string $resourceClass,
        string $operationType,
        string $operationName
    ): self {
        $openApiContext['x-message-class'] ??= $operation['message_class'];
        $openApiContext['x-resource-class'] ??= $resourceClass;
        $openApiContext['x-operation-type'] ??= $operationType;
        $openApiContext['x-operation-name'] ??= $operationName;

        return $this;
    }

    /**
     * @param class-string<ImmutableRecord> $messageClass
     */
    public static function typeDescription(
        string $messageClass,
        string $property,
        DocBlockFactory $docBlockFactory
    ): ?string {
        $reflectionClass = new ReflectionClass($messageClass);

        /** @var ReflectionNamedType|null $propertyType */
        $propertyType = $reflectionClass->hasProperty($property)
            ? $reflectionClass->getProperty($property)->getType()
            : null;

        if (isset($propertyType) && ! $propertyType->isBuiltin()) {
            // Get the description of the value object
            /** @var class-string $className */
            $className = $propertyType->getName();
            $propertyReflectionClass = new ReflectionClass($className);

            try {
                $docBlock = $docBlockFactory->create($propertyReflectionClass);

                return sprintf(
                    "%s\n %s",
                    $docBlock->getSummary(),
                    $docBlock->getDescription()->render()
                );
            } catch (InvalidArgumentException) {
            }
        }

        return null;
    }
}
