<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\Util\ArrayUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\OpenApi\Model\MediaType;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ArrayObject;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use function array_combine;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function in_array;
use function method_exists;
use function sprintf;
use function strpos;
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

    private ResourceMetadataFactoryInterface $decorated;
    private Config $config;
    private DocBlockFactory $docBlockFactory;
    private OperationPathResolverInterface $operationPathResolver;
    private SchemaFactoryInterface $schemaFactory;
    /** @var array<mixed> */
    private array $formats;

    /**
     * @param array<mixed> $formats
     */
    public function __construct(
        ResourceMetadataFactoryInterface $decorated,
        Config $config,
        OperationPathResolverInterface $operationPathResolver,
        array $formats
    ) {
        $this->decorated = $decorated;
        $this->config = $config;
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->operationPathResolver = $operationPathResolver;
        $this->formats = $formats;
    }

    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        $this->schemaFactory = $schemaFactory;
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
                    $resourceClass,
                    $operationType
                ) {
                    $messageClass = $messagesByOperationName[$operationName] ?? false;

                    if ($messageClass) {
                        if (! isset($operation['openapi_context'])) {
                            $operation['openapi_context'] = [];
                        }

                        $schema = OpenApiSchemaFactory::toOpenApiSchema($messageClass::__schema()->toArray());
                        $reflectionClass = new ReflectionClass($messageClass);
                        $openApiContext = &$operation['openapi_context'];

                        $this
                            ->addMessageClass($operation, $messageClass)
                            ->addHttpMethod($operation, $messageClass)
                            ->addPath($operation, $operationType, $resourceMetadata, $messageClass)
                            ->addController($operation, $messageClass)
                            ->addRead($operation)
                            ->addStateless($operation, $messageClass)
                            ->addStatus($operation, $messageClass, $reflectionClass)
                            ->addTags($openApiContext, $messageClass)
                            ->addDocumentation($openApiContext, $messageClass, $reflectionClass)
                            ->addParameters($operation, $schema, $messageClass)
                            ->addRequestBody(
                                $operation,
                                $operationType,
                                $operationName,
                                $schema,
                                $messageClass,
                                $resourceClass,
                                $resourceMetadata
                            )
                            ->addResponses(
                                $operation,
                                $messageClass,
                                $resourceClass,
                                $resourceMetadata,
                                $operationType,
                                $operationName,
                                $reflectionClass
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
        if (substr($path, -10) === '.{_format}') {
            $path = substr($path, 0, -10);
        }

        return strpos($path, '/') === 0 ? $path : '/' . $path;
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
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addDocumentation(
        array &$openApiContext,
        string $messageClass,
        ReflectionClass $reflectionClass
    ): self {
        try {
            $docBlock = $this->docBlockFactory->create($reflectionClass);
            $openApiContext['summary'] ??= $docBlock->getSummary();
            $openApiContext['description'] ??= $docBlock->getDescription()->render();
        } catch (InvalidArgumentException $exception) {
        }

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param array<mixed> $schema
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addParameters(
        array &$operation,
        array &$schema,
        string $messageClass
    ): self {
        $operation['openapi_context']['parameters'] ??= [];

        $uri = $messageClass::__path() ?? $operation['path'] ?? null;

        if ($uri === null) {
            return $this;
        }

        $path = Uri::fromString($uri);

        $parameters = [
            'path' => $path->toPathParameterNames(),
            'query' => $path->toQueryParameterNames(),
        ];

        foreach ($parameters as $type => $parameterNames) {
            if ($schema === null) {
                break;
            }

            foreach ($parameterNames as $parameterName) {
                if (! isset($schema['properties'][$parameterName])) {
                    throw new RuntimeException(
                        sprintf(
                            'Parameter \'%s\' (uri: %s) not found in message \'%s\'.',
                            $parameterName,
                            $uri,
                            $messageClass,
                        )
                    );
                }

                $operation['openapi_context']['parameters'][] = [
                    'name' => $parameterName,
                    'in' => $type,
                    'schema' => $schema['properties'][$parameterName],
                    'required' => in_array($parameterName, $schema['required']),
                ];
            }

            $schema = MessageSchemaFactory::removeParameters($schema, $parameterNames);
        }

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param array<mixed> $schema
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addRequestBody(
        array &$operation,
        string $operationType,
        string $operationName,
        ?array &$schema,
        string $messageClass,
        string $resourceClass,
        ResourceMetadata $resourceMetadata
    ): self {
        if ($schema === null && $operation['method'] !== Request::METHOD_POST) {
            return $this;
        }

        if (
            $schema
            && method_exists($messageClass, '__requestBodyArrayProperty')
            && $messageClass::__requestBodyArrayProperty()
        ) {
            $schema = $schema['properties'][$messageClass::__requestBodyArrayProperty()];
        }

        $content = [];
        $inputFormats = $this->formats($resourceMetadata, $operationType, $operationName);

        $context = isset($schema['properties'])
            ? [
                'allowed_properties' => array_keys(ArrayUtil::toCamelCasedKeys($schema['properties'])),
            ]
            : null;

        foreach ($inputFormats as $mimeType => $inputFormat) {
            $schema = $this->schemaFactory
                    ->buildSchema(
                        $resourceClass,
                        $inputFormat,
                        Schema::TYPE_INPUT,
                        $operationType,
                        $operationName,
                        null,
                        $context
                    )
                    ->getArrayCopy(false);

            $content[$mimeType] = new MediaType(new ArrayObject($schema));
        }

        $operation['openapi_context']['requestBody'] = [
            'required' => true,
            'content' => $content,
        ];

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addResponses(
        array &$operation,
        string $messageClass,
        string $resourceClass,
        ResourceMetadata $resourceMetadata,
        string $operationType,
        string $operationName,
        ReflectionClass $reflectionClass
    ): self {
        $operation['openapi_context']['responses'] ??= [];

        if (! $reflectionClass->implementsInterface(HasResponses::class)) {
            return $this;
        }

        $responses = $messageClass::__responseSchemasPerStatusCode();

        if ($operation['openapi_context']['requestBody'] ?? false) {
            $responses[SymfonyResponse::HTTP_BAD_REQUEST] = ApiPlatformException::badRequest();
        }

        $responseMimeTypes = $this->formats($resourceMetadata, $operationType, $operationName, 'output_formats');

        foreach ($responses as $statusCode => $responseSchema) {
            $description = '';
            $serializerContext = $statusCode === 'default'
                ? []
                : [
                    'status_code' => $statusCode,
                    'response_schema' => $responseSchema,
                ];

            $content = [];
            foreach ($responseMimeTypes as $mimeType => $format) {
                $schema = $this->schemaFactory
                    ->buildSchema(
                        $resourceClass,
                        $format,
                        Schema::TYPE_OUTPUT,
                        $operationType,
                        $operationName,
                        null,
                        $serializerContext
                    )
                    ->getArrayCopy(false);

                if ($mimeType === 'application/json') {
                    $description = $schema['description'] ?? '';
                }

                $content[$mimeType] = new MediaType(new ArrayObject($schema));
            }

            $operation['openapi_context']['responses'][$statusCode] = [
                'description' => $description,
                'content' => $content,
            ];
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    private function formats(
        ResourceMetadata $resourceMetadata,
        string $operationType,
        string $operationName,
        string $type = 'input_formats'
    ): array {
        $formats = $resourceMetadata->getTypedOperationAttribute(
            $operationType,
            $operationName,
            $type,
            $this->formats,
            true
        );

        return $this->flattenMimeTypes($formats);
    }

    /**
     * @param array<mixed> $formats
     *
     * @return array<mixed>
     */
    private function flattenMimeTypes(array $formats): array
    {
        $allMimeTypes = [];
        foreach ($formats as $responseFormat => $mimeTypes) {
            foreach ($mimeTypes as $mimeType) {
                $allMimeTypes[$mimeType] = $responseFormat;
            }
        }

        return $allMimeTypes;
    }
}
