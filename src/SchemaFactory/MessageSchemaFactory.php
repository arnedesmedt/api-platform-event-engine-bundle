<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\ComplexTypeExtractor;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\ResponseExtractor;
use ADS\JsonImmutableObjects\Polymorphism\Discriminator;
use ADS\Util\ArrayUtil;
use ADS\ValueObjects\ListValue;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_combine;
use function array_filter;
use function array_flip;
use function array_map;
use function array_merge;
use function array_values;
use function assert;
use function class_implements;
use function in_array;
use function is_callable;
use function method_exists;
use function sprintf;

final class MessageSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        private SchemaFactoryInterface $schemaFactory,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly ResponseExtractor $responseExtractor,
    ) {
        if ($this->schemaFactory instanceof SchemaFactoryAwareInterface) {
            $this->schemaFactory->setSchemaFactory($this);
        }

        $this->addDistinctFormat('jsonhal');
        $this->addDistinctFormat('jsonld');
    }

    /**
     * @param class-string<JsonSchemaAwareRecord|Discriminator> $className
     * @param array<string, mixed>|null $serializerContext
     * @param Schema<mixed>|null $schema
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        Operation|null $operation = null,
        Schema|null $schema = null,
        array|null $serializerContext = null,
        bool $forceCollection = false,
    ): Schema {
        $schema ??= new Schema();

        if (ComplexTypeExtractor::isClassComplexType($className)) {
            if ((new ReflectionClass($className))->implementsInterface(JsonSchemaAwareRecord::class)) {
                $complexSchema = new Schema();

                $complexSchema['type'] = $forceCollection
                    ? 'array'
                    : ComplexTypeExtractor::complexType($className);

                if ($forceCollection) {
                    $complexSchema['items'] = ['type' => ComplexTypeExtractor::complexType($className)];
                }

                $schema->getDefinitions()[$className::__type()] = new ArrayObject($complexSchema->getArrayCopy());
                $schema['$ref'] = sprintf(
                    $schema->getVersion() === Schema::VERSION_OPENAPI
                        ? '#/components/schemas/%s'
                        : '#/definitions/%s',
                    $className::__type(),
                );

                return $schema;
            }

            $schema['type'] = $forceCollection
                ? 'array'
                : ComplexTypeExtractor::complexType($className);

            if ($forceCollection) {
                $schema['items'] = ['type' => ComplexTypeExtractor::complexType($className)];
            }

            return $schema;
        }

        $input = $operation?->getInput();
        $messageClass = $input['class'] ?? null;
        $classReflectionClass = new ReflectionClass($className);

        if ($classReflectionClass->implementsInterface(Discriminator::class)) {
            /** @var class-string<Discriminator> $discriminatorClass */
            $discriminatorClass = $className;

            return $this->handleDiscriminator(
                $discriminatorClass,
                $format,
                $type,
                $schema,
            );
        }

        if (! $messageClass || $operation === null) {
            if ($operation === null && $this->isResourceClass($className)) {
                $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($className);

                /** @var ApiResource $resource */
                foreach ($resourceMetadataCollection as $resource) {
                    /** @var HttpOperation $possibleOperation */
                    foreach ($resource->getOperations() ?? [] as $possibleOperation) {
                        if ($possibleOperation->getMethod() === Request::METHOD_GET) {
                            $operation = $possibleOperation;
                            break 2;
                        }
                    }
                }
            }

            return $this->schemaFactory->buildSchema(
                $className,
                $format,
                $type,
                $operation,
                $schema,
                $serializerContext,
                $forceCollection,
            );
        }

        $reflectionClass = new ReflectionClass($messageClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            return $this->schemaFactory->buildSchema(
                $className,
                $format,
                $type,
                $operation,
                $schema,
                $serializerContext,
                $forceCollection,
            );
        }

        // OUTPUT
        if ($this->responseExtractor->hasResponsesFromReflectionClass($reflectionClass)) {
            $responseClass = $this->responseExtractor->defaultResponseClassFromReflectionClass($reflectionClass);

            $forceCollectionResponse = false;
            if (in_array(ListValue::class, class_implements($responseClass) ?: [])) {
                // @phpstan-ignore-next-line
                $responseClass = $responseClass::itemType();
                $forceCollectionResponse = true;
            }

            if (ComplexTypeExtractor::isClassComplexType($responseClass)) {
                $schema['type'] = $forceCollectionResponse
                    ? 'array'
                    : ComplexTypeExtractor::complexType($responseClass);

                if ($forceCollectionResponse) {
                    $schema['items'] = ['type' => ComplexTypeExtractor::complexType($className)];
                }
            }

            $this->schemaFactory->buildSchema(
                $responseClass,
                $format,
                Schema::TYPE_OUTPUT,
                $operation,
                $schema,
                null,
                $forceCollectionResponse,
            );
        }

        assert($operation instanceof HttpOperation);

        if (
            in_array($operation->getMethod(), [Request::METHOD_GET, Request::METHOD_OPTIONS])
            || $schema->getVersion() === Schema::VERSION_JSON_SCHEMA
            // used for testing the response where we don't need the input
        ) {
            return $schema;
        }

        // INPUT
        self::updateOperationForLists(
            $messageClass,
            $reflectionClass,
            $input,
            $operation,
            $forceCollection,
        );

        $messageClass = $operation->getInput()['class'] ?? null;
        $reflectionClass = new ReflectionClass($messageClass);

        if ($reflectionClass->implementsInterface(Discriminator::class)) {
            return $this->handleDiscriminator($messageClass, $format, $type, $schema);
        }

        $inputSchema = $this->schemaFactory->buildSchema(
            $className,
            $format,
            Schema::TYPE_INPUT,
            $operation->withMethod(Request::METHOD_PUT),
            new Schema(Schema::VERSION_OPENAPI),
            $serializerContext,
            $forceCollection,
        );

        $inputDefinitions = $inputSchema->getDefinitions();
        /** @var string $rootDefinitionKey */
        $rootDefinitionKey = $inputSchema->getRootDefinitionKey() ?? $inputSchema->getItemsDefinitionKey();
        /** @var ArrayObject<string, mixed> $definition */
        $definition = $inputDefinitions->offsetGet($rootDefinitionKey);
        $definition = $definition->getArrayCopy();
        /** @var string $uriTemplate */
        $uriTemplate = $operation->getUriTemplate();
        /** @var array<string> $parameterNames */
        $parameterNames = ArrayUtil::toSnakeCasedValues(Uri::fromString($uriTemplate)->toAllParameterNames());
        $definition = self::removeParameters(
            $definition,
            $parameterNames,
        ) ?? ['type' => 'object'];
        $inputDefinitions[$rootDefinitionKey] = new ArrayObject($definition);

        $definitions = $schema->getDefinitions();
        $schema->setDefinitions(new ArrayObject([...$definitions, ...$inputDefinitions]));

        return $schema;
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    public static function removeParameters(array $schema, array $parameterNames): array|null
    {
        return self::changeParameters($schema, $parameterNames);
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parametersNames
     *
     * @return array<string, mixed>|null
     */
    public static function filterParameters(array $schema, array $parametersNames): array|null
    {
        return self::changeParameters($schema, $parametersNames, 'array_intersect');
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    private static function changeParameters(
        array $schema,
        array $parameterNames,
        string $filterMethod = 'array_diff',
    ): array|null {
        $keyFilterMethod = sprintf('%s_key', $filterMethod);

        if (! is_callable($keyFilterMethod) || ! is_callable($filterMethod)) {
            throw new RuntimeException(
                sprintf(
                    'Method \'%s\' or \'%s\' is not callable',
                    $keyFilterMethod,
                    $filterMethod,
                ),
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

    public function addDistinctFormat(string $format): void
    {
        if (! method_exists($this->schemaFactory, 'addDistinctFormat')) {
            return;
        }

        $this->schemaFactory->addDistinctFormat($format);
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param ReflectionClass<object> $reflectionClass
     * @param array<string, mixed> $input
     */
    public static function updateOperationForLists(
        string $messageClass,
        ReflectionClass $reflectionClass,
        array $input,
        HttpOperation &$operation,
        bool &$forceCollection,
    ): void {
        $requestBodyArrayProperty = $messageClass::__requestBodyArrayProperty();
        if (! $requestBodyArrayProperty) {
            return;
        }

        $arrayProperty = $reflectionClass->getProperty($requestBodyArrayProperty);
        /** @var ReflectionNamedType|null $reflectionNamedType */
        $reflectionNamedType = $arrayProperty->getType();
        /** @var class-string<ListValue<object>>|null $listClass */
        $listClass = $reflectionNamedType?->getName();

        if ($listClass === null) {
            throw new RuntimeException(
                sprintf(
                    'No class type found for property \'%s\'.',
                    $requestBodyArrayProperty,
                ),
            );
        }

        if (in_array(ListValue::class, class_implements($listClass) ?: [])) {
            $listClass = $listClass::itemType();
        }

        /** @var HttpOperation $operation */
        $operation = $operation->withInput(array_merge($input, ['class' => $listClass]));
        $forceCollection = true;
    }

    /**
     * @param class-string<Discriminator> $className
     * @param Schema<mixed> $schema
     *
     * @return Schema<mixed>
     */
    private function handleDiscriminator(
        string $className,
        string $format,
        string $type,
        Schema $schema,
    ): Schema {
        $definitionName = $className::__type() . ($format === 'json' ? '' : '.' . $format);
        $ref = sprintf(
            $schema->getVersion() === Schema::VERSION_OPENAPI
                ? '#/components/schemas/%s'
                : '#/definitions/%s',
            $definitionName,
        );

        $definitionNames = array_combine(
            $className::jsonSchemaAwareRecords(),
            array_map(
                function (string $modelClass) use ($format, $type, &$schema) {
                    unset($schema['$ref']);
                    $schema = $this->schemaFactory->buildSchema(
                        $modelClass,
                        $format,
                        $type,
                        null,
                        $schema,
                    );

                    return $schema->getArrayCopy(false);
                },
                $className::jsonSchemaAwareRecords(),
            ),
        );

        $schema['$ref'] = $ref;
        $definitions = $schema->getDefinitions();
        $definitions[$definitionName] = [
            'oneOf' => array_values($definitionNames),
            'discriminator' => [
                'propertyName' => $className::propertyName(),
                'mapping' => array_filter(
                    array_map(
                        static fn (string $oneOfClass) => $definitionNames[$oneOfClass]['$ref'] ?? null,
                        $className::mapping(),
                    ),
                ),
            ],
        ];

        return $schema;
    }

    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($schemaFactory);
    }
}
