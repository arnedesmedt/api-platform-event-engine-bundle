<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Bundle\EventEngineBundle\Config;
use ADS\Bundle\EventEngineBundle\Exception\ResponseException;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\Util\ArrayUtil;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use EventEngine\EventEngine;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

use function array_flip;
use function array_merge;
use function array_merge_recursive;
use function array_unique;
use function array_values;
use function is_callable;
use function iterator_to_array;
use function sprintf;

final class MessageSchemaFactory implements SchemaFactoryInterface
{
    private SchemaFactoryInterface $schemaFactory;
    private Finder $messageFinder;
    private Config $config;
    private EventEngine $eventEngine;
    private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory;
    /** @var array<mixed> */
    private array $defaults;

    /**
     * @param array<mixed> $defaults
     */
    public function __construct(
        SchemaFactoryInterface $schemaFactory,
        Finder $messageFinder,
        Config $config,
        EventEngine $eventEngine,
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        array $defaults
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->messageFinder = $messageFinder;
        $this->config = $config;
        $this->eventEngine = $eventEngine;
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->defaults = $defaults;
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
        $message = $this->message($className, $operationType, $operationName);

        if (! $message || $operationType === null || $operationName === null) {
            return $this->schemaFactory->buildSchema(
                $className,
                $format,
                $type,
                $operationType,
                $operationName,
                $schema,
                $serializerContext,
                $forceCollection
            );
        }

        // Set the defaults
        $schema ??= new Schema(Schema::VERSION_OPENAPI);
        $serializerContext ??= [];

        $reflectionClass = new ReflectionClass($message);
        if ($type === Schema::TYPE_OUTPUT && ! $reflectionClass->implementsInterface(HasResponses::class)) {
            return $schema;
        }

        $definitions = $schema->getDefinitions();
        $schema = new Schema(Schema::VERSION_OPENAPI);
        $schema->setDefinitions($definitions);
        $schemaArray = $schema->getArrayCopy();

        $openApiSchema = $this->openApiSchema($type, $message, $serializerContext);
        $openApiSchemaArray = $openApiSchema->toArray();

        $schemaArray = array_merge_recursive(
            $schemaArray,
            $openApiSchemaArray
        );

        $schemaArray = OpenApiSchemaFactory::toOpenApiSchema($schemaArray);
        $refs = OpenApiSchemaFactory::findTypeRefs($schemaArray);

        if (empty($refs)) {
            $schema->exchangeArray($schemaArray);

            return $schema;
        }

        $serializerContext += $this->serializerContext($message, $type);
        $responseTypes = $this->config->config()['responseTypes'];

        foreach ($refs as $ref) {
            if (! $this->eventEngine->isKnownType($ref)) {
                continue;
            }

            if ($format !== 'json') {
                $formatRef = sprintf('%s-%s', $ref, $format);
                OpenApiSchemaFactory::replaceRefs($schemaArray, $ref, $formatRef);

                if (isset($definitions[$ref]) && isset($definitions[$formatRef])) {
                    continue;
                }

                if (isset($definitions[$ref])) {
                    $definitions[$formatRef] = $definitions[$ref];
                    continue;
                }
            }

            $schemaRef = $this->filterProperties(
                $className,
                $message,
                $type,
                $responseTypes[$ref],
                $serializerContext,
                $operationType,
                $operationName
            );

            $definitions[$ref] = OpenApiSchemaFactory::toOpenApiSchema($schemaRef);

            if (! isset($formatRef)) {
                continue;
            }

            $definitions[$formatRef] = $definitions[$ref];
        }

        $schema->exchangeArray($schemaArray);

        return $schema;
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
     * @param array<string, mixed> $serializerContext
     */
    private function openApiSchema(string $type, string $message, array $serializerContext): TypeSchema
    {
        if ($type === Schema::TYPE_INPUT) {
            return $message::__schema();
        }

        if (isset($serializerContext['status_code'])) {
            if (isset($serializerContext['response_schema'])) {
                return $serializerContext['response_schema'];
            }

            try {
                return $message::__responseSchemaForStatusCode($serializerContext['status_code']);
            } catch (ResponseException $exception) {
            }
        }

        return $message::__defaultResponseSchema();
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

        return $filteredSchema;
    }

    /**
     * @return array<mixed>
     */
    private function serializerContext(
        string $messageClass,
        string $type = Schema::TYPE_OUTPUT
    ): array {
        $contextType = $type === Schema::TYPE_OUTPUT ? 'normalization' : 'denormalization';
        $method = sprintf('__%sContext', $contextType);
        $attribute = sprintf('%s_context', $contextType);
        $context = $messageClass::$method();

        if (! empty($context)) {
            return $context;
        }

        return $this->defaults['attributes'][$attribute] ?? [];
    }

    /**
     * @param array<mixed> $schemaArray
     * @param array<mixed> $serializerContext
     *
     * @return array<mixed>
     */
    private function filterProperties(
        string $className,
        string $message,
        string $type,
        array $schemaArray,
        array $serializerContext,
        ?string $operationType,
        ?string $operationName
    ): array {
        $options = $this->options($serializerContext, $operationType, $operationName);
        $inputOrOutputClass = $this->inputOrOutputClass($message, $type, $className);
        $propertyNames = $this->propertyNameCollectionFactory->create($inputOrOutputClass, $options);
        $filteredSchemaArray = self::filterParameters(
            $schemaArray,
            array_unique(
                array_merge(
                    $serializerContext['allowed_properties'] ?? [],
                    ArrayUtil::toCamelCasedValues(
                        iterator_to_array($propertyNames->getIterator())
                    )
                )
            )
        );

        if (empty($filteredSchemaArray)) {
            return $schemaArray;
        }

        return $filteredSchemaArray;
    }

    /**
     * @param array<mixed> $serializerContext
     *
     * @return array<string, mixed>
     */
    private function options(array $serializerContext, ?string $operationType, ?string $operationName): array
    {
        $options = [];

        if (isset($serializerContext[AbstractNormalizer::GROUPS])) {
            $options['serializer_groups'] = (array) $serializerContext[AbstractNormalizer::GROUPS];
        }

        if ($operationType !== null && $operationName !== null) {
            $options[sprintf('%s_operation_name', $operationType)] = $operationName;
        }

        return $options;
    }

    private function inputOrOutputClass(string $messageClass, string $type, string $className): string
    {
        $method = sprintf('__%sClass', $type);

        return $messageClass::$method() ?? $className;
    }
}
