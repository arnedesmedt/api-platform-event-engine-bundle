<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
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

    public function __construct(
        ResourceMetadataFactoryInterface $decorated,
        Config $config,
        OperationPathResolverInterface $operationPathResolver
    ) {
        $this->decorated = $decorated;
        $this->config = $config;
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->operationPathResolver = $operationPathResolver;
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
                    $operationType
                ) {
                    $messageClass = $messagesByOperationName[$operationName] ?? false;

                    if ($messageClass) {
                        if (! isset($operation['openapi_context'])) {
                            $operation['openapi_context'] = [];
                        }

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
                            ->addInputClass($operation, $messageClass)
                            ->addOutputClass($operation, $messageClass)
                            ->addTags($openApiContext, $messageClass)
                            ->addDocumentation($openApiContext, $reflectionClass)
                            ->addParameters($operation, $messageClass);
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
     * @param array<mixed> $operation
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
     * @param array<mixed> $operation
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
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addDocumentation(
        array &$openApiContext,
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
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addParameters(
        array &$operation,
        string $messageClass
    ): self {
        $operation['openapi_context']['parameters'] ??= [];

        $pathUri = $messageClass::__pathUri() ?? ($operation['path'] ? Uri::fromString($operation['path']) : null);

        if ($pathUri === null) {
            return $this;
        }

        $schema = $messageClass::__schema()->toArray();

        $allParameterNames = $pathUri->toAllParameterNames();
        $pathParameterNames = $pathUri->toPathParameterNames();

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
            $propertySchema = $pathSchema['properties'][$parameterName];
            $in = in_array($parameterName, $pathParameterNames) ? 'path' : 'query';
            $name = StringUtil::decamelize($parameterName);

            if ($in === 'path' && isset($propertySchema['pattern'])) {
                $operation['requirements'][$name] = $propertySchema['pattern'];
            }

            $operation['openapi_context']['parameters'][] = [
                'name' => $name,
                'in' => $in,
                'schema' => OpenApiSchemaFactory::toOpenApiSchema($propertySchema),
                'required' => in_array($parameterName, $pathSchema['required']),
            ];
        }

        return $this;
    }
}
