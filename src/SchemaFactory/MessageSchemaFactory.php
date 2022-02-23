<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Util\ArrayUtil;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\OpenApi\Factory\OpenApiFactory;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_flip;
use function array_merge;
use function array_values;
use function in_array;
use function is_callable;
use function method_exists;
use function sprintf;

final class MessageSchemaFactory implements SchemaFactoryInterface
{
    public const COMMAND_METHODS = [
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PATCH,
        Request::METHOD_PUT,
    ];

    public function __construct(
        private SchemaFactoryInterface $schemaFactory,
        private Finder $messageFinder,
        private ResourceMetadataFactoryInterface $resourceMetadataFactory,
        private ResourceClassResolverInterface $resourceClassResolver
    ) {
    }

    /**
     * @param array<string, mixed>|null $serializerContext
     * @param Schema<mixed> $schema
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        ?string $operationType = null,
        ?string $operationName = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false
    ): Schema {
        /** @var array<string, array<string, mixed>>|null $response */
        $response = $serializerContext['response'] ?? null;

        // Set the defaults
        $schema = $schema ? clone $schema : new Schema();
        $serializerContext ??= [];
        $serializerContext['type'] ??= $type; // fix to pass type to the sub schema factory

        /** @var ResourceMetadata|null $resourceMetadata */
        $resourceMetadata = $this->resourceClassResolver->isResourceClass($className)
            ? $this->resourceMetadataFactory->create($className)
            : null;
        $httpMethod = $this->httpMethod($type, $operationType, $operationName, $resourceMetadata);

        if ($this->isCommandAndOutput($type, $httpMethod, $serializerContext)) {
            $this->addEmptyDefinition($schema, $format);

            return $schema;
        }

        if (
            isset($response)
            && (
                ! ($serializerContext['isDefaultResponse'] ?? true)
                || ! (isset($response['$ref']) || isset($response['items']['$ref']))
            )
        ) {
            $schema = OpenApiSchemaFactory::toApiPlatformSchema($response);

            return $schema;
        }

        $this->appendNormalizationContext(
            $serializerContext,
            $resourceMetadata,
            $type,
            $operationType,
            $operationName
        );

        $message = $this->message($className, $operationType, $operationName);

        // Move from resource to a command message for input schema's
        if (
            $type === Schema::TYPE_INPUT
            && $resourceMetadata !== null
            && $operationType !== null
            && $operationName !== null
        ) {
            $className = $message ?? $className;

            $path = Uri::fromString(
                $resourceMetadata->getTypedOperationAttribute($operationType, $operationName, 'path')
            );

            $operationType = $operationName = null;
        }

        // If another output state is used then the default state.
        if (
            $type === Schema::TYPE_OUTPUT
            && $message !== null
            && method_exists($message, '__schemaStateClass')
            && $message::__schemaStateClass()
        ) {
            $className = $message::__schemaStateClass();
            if ($operationType === OperationType::COLLECTION) {
                $forceCollection = true;
            }

            $operationType = $operationName = null;
        }

        $this->setDefinitionName(
            $serializerContext,
            $resourceMetadata,
            $className,
            $format
        );

        $schema = $this->schemaFactory->buildSchema(
            $className,
            $format,
            $type,
            $operationType,
            $operationName,
            $schema,
            $serializerContext,
            $forceCollection
        );

        if (! isset($path)) {
            return $schema;
        }

        return $this->removePathParameterFromSchema($schema, $path);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isCommandAndOutput(string $type, string $httpMethod, array $context): bool
    {
        /** @var array<string, mixed> $response */
        $response = $context['response'] ?? [];

        return $type === Schema::TYPE_OUTPUT
            && in_array($httpMethod, self::COMMAND_METHODS)
            && empty($response['properties'] ?? [])
            && ($context['isDefaultResponse'] ?? true);
    }

    private function refName(string $version, string $definitionName): string
    {
        return $version === Schema::VERSION_OPENAPI
            ? '#/components/schemas/' . $definitionName
            : '#/definitions/' . $definitionName;
    }

    /**
     * @return class-string|null
     */
    private function message(?string $resourceClass, ?string $operationType, ?string $operationName): ?string
    {
        if ($resourceClass === null || $operationType === null || $operationName === null) {
            return null;
        }

        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext(
                [
                    'resource_class' => $resourceClass,
                    'operation_type' => $operationType,
                    sprintf('%s_operation_name', $operationType) => $operationName,
                ]
            );
        } catch (FinderException) {
            return null;
        }

        $reflectionClass = new ReflectionClass($message);

        return $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class) ? $message : null;
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    public static function removeParameters(array $schema, array $parameterNames): ?array
    {
        return self::changeParameters($schema, $parameterNames);
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parametersNames
     *
     * @return array<string, mixed>|null
     */
    public static function filterParameters(array $schema, array $parametersNames): ?array
    {
        return self::changeParameters($schema, $parametersNames, 'array_intersect');
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    private static function changeParameters(
        array $schema,
        array $parameterNames,
        string $filterMethod = 'array_diff'
    ): ?array {
        $keyFilterMethod = sprintf('%s_key', $filterMethod);

        if (! is_callable($keyFilterMethod) || ! is_callable($filterMethod)) {
            throw new RuntimeException(
                sprintf(
                    'Method \'%s\' or \'%s\' is not callable',
                    $keyFilterMethod,
                    $filterMethod
                )
            );
        }

        $schema['properties'] ??= [];
        $schema['required'] ??= [];

        $filteredSchema = $schema;
        $filteredSchema['properties'] = $keyFilterMethod($schema['properties'], array_flip($parameterNames));

        if (empty($filteredSchema['properties'])) {
            return null;
        }

        $filteredSchema['required'] = array_values($filterMethod($schema['required'], $parameterNames));

        if (empty($filteredSchema['required'])) {
            unset($filteredSchema['required']);
        }

        return $filteredSchema;
    }

    /**
     * @param array<string, string> $serializerContext
     * @param class-string $className
     */
    private function setDefinitionName(
        array &$serializerContext,
        ?ResourceMetadata $resourceMetadata,
        string $className,
        string $format
    ): void {
        $previousDefinitionName = $serializerContext['previous_definition_name'] ?? '';
        $serializerContext['previous_definition_name'] = sprintf(
            '%s%s',
            isset($resourceMetadata)
                ? $resourceMetadata->getShortName()
                : (new ReflectionClass($className))->getShortName(),
            $previousDefinitionName ? sprintf('-%s', $previousDefinitionName) : ''
        );

        $serializerContext[OpenApiFactory::OPENAPI_DEFINITION_NAME] = sprintf(
            '%s%s%s%s',
            $previousDefinitionName,
            $previousDefinitionName ? '-' : '',
            $serializerContext['type'] === Schema::TYPE_INPUT
                ? (
            $previousDefinitionName
                ? 'Input'
                : sprintf('%s-Input', StringUtil::entityNameFromClassName($className))
            )
                : 'Output',
            $this->formatExtension($format)
        );
    }

    private function httpMethod(
        string $type,
        ?string $operationType = null,
        ?string $operationName = null,
        ?ResourceMetadata $resourceMetadata = null
    ): string {
        if ($operationType === null || $operationName === null) {
            return $type === Schema::TYPE_INPUT ? Request::METHOD_POST : Request::METHOD_GET;
        }

        if ($resourceMetadata === null) {
            throw new RuntimeException(
                'We cannot get the http method if the resource meta data ' .
                'and the opration type and name are empty.'
            );
        }

        return $resourceMetadata->getTypedOperationAttribute($operationType, $operationName, 'method');
    }

    /**
     * @param array<mixed> $serializerContext
     */
    private function appendNormalizationContext(
        array &$serializerContext,
        ?ResourceMetadata $resourceMetadata,
        string $type,
        ?string $operationType,
        ?string $operationName
    ): void {
        if ($resourceMetadata === null || $operationType === null || $operationName === null) {
            return;
        }

        $key = sprintf('%snormalization_context', $type === Schema::TYPE_INPUT ? 'de' : '');
        $extraContext = $resourceMetadata->getTypedOperationAttribute(
            $operationType,
            $operationName,
            $key,
            [],
            true
        );

        $serializerContext = array_merge(
            $serializerContext,
            $extraContext
        );
    }

    /**
     * @param Schema<string, mixed> $schema
     */
    private function addEmptyDefinition(Schema $schema, string $format): void
    {
        // TODO not every output needs to be an empty object. Verify it against the command.
        $formatExtension = $this->formatExtension($format);
        $emptyDefinitionName = sprintf('EmptyObject%s', $formatExtension);
        $definitions = $schema->getDefinitions();

        // CQRS => Commands have no output
        if (! isset($definitions[$emptyDefinitionName])) {
            /** @var ArrayObject<string, mixed> $definition */
            $definition = new ArrayObject(['type' => 'object']);
            $definitions[$emptyDefinitionName] = $definition;
        }

        $schema['$ref'] = $this->refName($schema->getVersion(), $emptyDefinitionName);
    }

    private function formatExtension(string $format): string
    {
        return $format === 'json' ? '' : '.' . $format;
    }

    /**
     * @param Schema<string, mixed> $schema
     *
     * @return Schema<string, mixed>
     */
    private function removePathParameterFromSchema(Schema $schema, Uri $path): Schema
    {
        // remove the path parameters from the schema
        /** @var array<string> $pathParameters */
        $pathParameters = ArrayUtil::toSnakeCasedValues($path->toAllParameterNames());
        $definitions = $schema->getDefinitions();

        /** @var string $rootDefinitionKey */
        $rootDefinitionKey = $schema->getRootDefinitionKey();
        $definition = $definitions->offsetGet($rootDefinitionKey);
        $definition = self::removeParameters($definition->getArrayCopy(), $pathParameters);

        if ($definition === null) {
            return new Schema(Schema::VERSION_OPENAPI);
        }

        $definition = new ArrayObject($definition);
        $definitions->offsetSet($schema->getRootDefinitionKey(), $definition);

        return $schema;
    }
}
