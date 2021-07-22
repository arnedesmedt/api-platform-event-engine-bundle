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
use function assert;
use function in_array;
use function is_callable;
use function is_string;
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

    private SchemaFactoryInterface $schemaFactory;
    private Finder $messageFinder;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private ResourceClassResolverInterface $resourceClassResolver;

    public function __construct(
        SchemaFactoryInterface $schemaFactory,
        Finder $messageFinder,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->messageFinder = $messageFinder;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->resourceClassResolver = $resourceClassResolver;
    }

    /**
     * @param array<mixed>|null $serializerContext
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
        // Set the defaults
        $schema = $schema ? clone $schema : new Schema();
        $serializerContext ??= [];

        $version = $schema->getVersion();
        $definitions = $schema->getDefinitions();
        $formatExtension = $format === 'json' ? '' : '.' . $format;
        $emptyDefinitionName = sprintf('EmptyObject%s', $formatExtension);

        if ($this->resourceClassResolver->isResourceClass($className)) {
            assert(is_string($operationType));
            assert(is_string($operationName));
            $resourceMetadata = $this->resourceMetadataFactory->create($className);
            $httpMethod = $this->httpMethod($type, $operationType, $operationName, $resourceMetadata);

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

            if ($type === Schema::TYPE_OUTPUT && in_array($httpMethod, self::COMMAND_METHODS)) {
                // CQRS => Commands have no output
                if (! isset($definitions[$emptyDefinitionName])) {
                    /** @var ArrayObject<string, mixed> $definition */
                    $definition = new ArrayObject(['type' => 'object']);
                    $definitions[$emptyDefinitionName] = $definition;
                }

                $schema['$ref'] = $this->refName($version, $emptyDefinitionName);

                return $schema;
            }

            $message = $this->message($className, $operationType, $operationName);

            if ($type === Schema::TYPE_INPUT) {
                $className = $message ?? $className;

                $path = Uri::fromString(
                    $resourceMetadata->getTypedOperationAttribute($operationType, $operationName, 'path')
                );

                $operationType = $operationName = null;
            }

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
        }

        $serializerContext['type'] ??= $type; // fix to pass type to the sub schema factory
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
            $formatExtension
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

        if (isset($path)) {
            // remove the path parameters from the schema
            $pathParameters = ArrayUtil::toSnakeCasedValues($path->toAllParameterNames());

            $definition = $definitions->offsetGet($schema->getRootDefinitionKey());
            $definition = self::removeParameters($definition->getArrayCopy(), $pathParameters);

            if ($definition === null) {
                return new Schema(Schema::VERSION_OPENAPI);
            }

            $definition = new ArrayObject($definition);
            $definitions->offsetSet($schema->getRootDefinitionKey(), $definition);
        }

        return $schema;
    }

    private function refName(string $version, string $definitionName): string
    {
        return $version === Schema::VERSION_OPENAPI
            ? '#/components/schemas/' . $definitionName
            : '#/definitions/' . $definitionName;
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
     * @return class-string|null
     */
    private function message(string $resourceClass, ?string $operationType, ?string $operationName): ?string
    {
        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext(
                [
                    'resource_class' => $resourceClass,
                    'operation_type' => $operationType,
                    sprintf('%s_operation_name', $operationType) => $operationName,
                ]
            );
        } catch (FinderException $exception) {
            return null;
        }

        $reflectionClass = new ReflectionClass($message);

        return $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class) ? $message : null;
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<mixed>|null
     */
    public static function removeParameters(array $schema, array $parameterNames): ?array
    {
        return self::changeParameters($schema, $parameterNames);
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parametersNames
     *
     * @return array<mixed>|null
     */
    public static function filterParameters(array $schema, array $parametersNames): ?array
    {
        return self::changeParameters($schema, $parametersNames, 'array_intersect');
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $parameterNames
     *
     * @return array<mixed>|null
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
}
