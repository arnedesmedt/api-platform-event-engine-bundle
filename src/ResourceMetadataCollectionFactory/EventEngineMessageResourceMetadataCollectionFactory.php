<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineState;
use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CallbackMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Processor\CommandProcessor;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreCollectionProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreItemProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\RequestMessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\CommandExtractor;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\QueryExtractor;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\ResponseExtractor;
use ADS\Bundle\EventEngineBundle\Type\ComplexTypeExtractor;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\OperationDefaultsTrait;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Util\CamelCaseToSnakeCaseNameConverter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use LogicException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

use function array_key_exists;
use function array_map;
use function array_shift;
use function class_implements;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function reset;
use function sprintf;

final class EventEngineMessageResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    use OperationDefaultsTrait;

    private readonly DocBlockFactory $docBlockFactory;

    /** @param array<mixed> $defaults */
    public function __construct(
        #[Autowire('@api_platform.metadata.resource.metadata_collection_factory.attributes')]
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly Config $config,
        #[Autowire('@property_info')]
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly CommandExtractor $commandExtractor,
        private readonly QueryExtractor $queryExtractor,
        private readonly ResponseExtractor $responseExtractor,
        #[Autowire('%api_platform.defaults%')]
        array $defaults = [],
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->camelCaseToSnakeCaseNameConverter = new CamelCaseToSnakeCaseNameConverter();
        $this->defaults = $defaults;
    }

    /** @param class-string<JsonSchemaAwareRecord> $resourceClass */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $messageMapping = $this->config->messageMapping();

        // if no event engine messages are found linked with this resource.
        if (! array_key_exists($resourceClass, $messageMapping)) {
            return $this->resourceMetadataCollectionFactory->create($resourceClass);
        }

        $resourceMetadataCollection = new ResourceMetadataCollection($resourceClass);

        /** @var array<class-string<ApiPlatformMessage>> $messages */
        $messages = $messageMapping[$resourceClass];
        $reflectionClass = new ReflectionClass($resourceClass);
        $eventEngineStates = $reflectionClass->getAttributes(
            EventEngineState::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

        foreach ($eventEngineStates as $eventEngineState) {
            $resource = $this->getResourceWithDefaults(
                $resourceClass,
                $resourceClass::__type(),
                $eventEngineState->newInstance(),
            );

            $operations = [];
            foreach ($messages as $messageClass) {
                $messageReflectionClass = new ReflectionClass($messageClass);
                $operationAttributes = $messageReflectionClass->getAttributes(
                    Operation::class,
                    ReflectionAttribute::IS_INSTANCEOF,
                );

                if (empty($operationAttributes)) {
                    throw new LogicException(
                        sprintf('No api platform operation found for message \'%s\'', $messageClass),
                    );
                }

                if (count($operationAttributes) > 1) {
                    throw new LogicException(
                        sprintf('Multiple api platform operations found for message \'%s\'', $messageClass),
                    );
                }

                /** @var ReflectionAttribute<HttpOperation> $operationAttribute */
                $operationAttribute = reset($operationAttributes);
                $operation = $operationAttribute->newInstance()->withName($messageClass::__operationId());

                $inputClass = $messageClass;

//                $requestBodyArrayProperty = $messageClass::__requestBodyArrayProperty();
//                if ($requestBodyArrayProperty) {
//                    $arrayProperty = $messageReflectionClass->getProperty($requestBodyArrayProperty);
//                    /** @var ReflectionNamedType|null $reflectionNamedType */
//                    $reflectionNamedType = $arrayProperty->getType();
//                    /** @var class-string<ListValue<object>>|null $listClass */
//                    $listClass = $reflectionNamedType?->getName();
//
//                    if ($listClass === null) {
//                        throw new LogicException(
//                            sprintf(
//                                'No class type found for property \'%s\'.',
//                                $requestBodyArrayProperty,
//                            ),
//                        );
//                    }
//
//                    $inputClass = $listClass;

//                    if (in_array(ListValue::class, class_implements($listClass) ?: [])) {
//                        $listClass = $listClass::itemType();
//                    }
//
//                    /** @var HttpOperation $operation */
//                    $operation = $operation->withInput(array_merge($operation, ['class' => $listClass]));
//                    $forceCollection = true;
//                }

                /** @var array<class-string> $messageInterfaces */
                $messageInterfaces = class_implements($messageClass) ?: [];

                [$key, $operation] = $this->getOperationWithDefaults(
                    $resource,
                    $operation,
                );

                $operation = $operation
                    ->withShortName($messageClass::__schemaStateClass()::__type())
                    ->withClass($resourceClass)
                    ->withUriTemplate('/' . ltrim(Uri::fromString($messageClass::__uriTemplate())->toUrlPart(), '/'))
//                    ->withUriVariables(null) // todo
                    ->withRead($this->queryExtractor->isQueryFromReflectionClass($messageReflectionClass))
                    ->withWrite($this->commandExtractor->isCommandFromReflectionClass($messageReflectionClass))
//                    ->withSerialize(null) // todo
//                    ->withValidate(null) // todo
                    ->withStatus($this->defaultStatusCode($messageReflectionClass))
                    ->withInput(['class' => $inputClass])
                    ->withOutput(
                        $resourceClass !== $messageClass::__schemaStateClass()
                            ? ['class' => $messageClass::__schemaStateClass()]
                            : null,
                    );

                if (! $operation->getProcessor()) {
                    $operation = $operation->withProcessor(CommandProcessor::class);
                }

                if (! $operation->getProvider() && $operation->canRead()) {
                    $operation = $operation->withProvider(
                        $operation instanceof CollectionOperationInterface
                                    ? DocumentStoreCollectionProvider::class
                                    : DocumentStoreItemProvider::class,
                    );
                }

                $this->openApi($operation, $messageClass, $messageInterfaces);

                $operations[$key] = $operation;
            }

            $resourceMetadataCollection[] = $resource->withOperations(new Operations($operations));
        }

        return $resourceMetadataCollection;
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param array<class-string> $messageInterfaces
     */
    private function openApi(HttpOperation &$operation, string $messageClass, array $messageInterfaces): void
    {
        $description = explode('\n', $operation->getDescription() ?? '');
        $summary = array_shift($description);

        $openApiOperation = new OpenApiOperation(
            operationId: $operation->getName(),
            tags: $messageClass::__tags(),
            summary: $summary,
            description: implode('\n', $description),
            callbacks: $this->buildCallbacks($messageClass, $messageInterfaces),
            parameters: $this->parameters($messageClass),
        );

        $operation = $operation->withOpenapi($openApiOperation);
    }

    /**
     * @param class-string<ApiPlatformMessage>  $messageClass
     * @param array<class-string> $messageInterfaces
     *
     * @return ArrayObject<string, mixed>|null
     */
    private function buildCallbacks(string $messageClass, array $messageInterfaces): ArrayObject|null
    {
        if (! in_array(CallbackMessage::class, $messageInterfaces)) {
            return null;
        }

        /** @var class-string<ApiPlatformMessage&CallbackMessage> $callbackMessageClass */
        $callbackMessageClass = $messageClass;

        /** @var array<string, class-string<JsonSchemaAwareRecord>> $events */
        $events = $callbackMessageClass::__callbackEvents();

        /** @var ArrayObject<string, mixed> $arrayObject */
        $arrayObject = new ArrayObject(
            array_map(
                /** @param class-string<JsonSchemaAwareRecord> $schemaClass */
                static fn (string $schemaClass) => [
                    '{$request.body#/callback_url}' => [
                        'post' => [
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    'application/json' => [
                                        'schema' => OpenApiSchemaFactory::toOpenApiSchema(
                                            $schemaClass::__schema()->toArray(),
                                        ),
                                    ],
                                ],
                            ],
                            'responses' => [
                                '200' => ['description' => 'Your server returns a 200 OK, if it accepts the callback.'],
                            ],
                        ],
                    ],
                ],
                $events,
            ),
        );

        return $arrayObject;
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     *
     * @return array<Parameter>
     */
    private function parameters(
        string $messageClass,
    ): array|null {
        $pathUri = Uri::fromString($messageClass::__uriTemplate());
        $schema = $messageClass::__schema()->toArray();

        $allParameterNames = $pathUri->toAllParameterNames();
        $pathParameterNames = $pathUri->toPathParameterNames();

        /** @var array<string, array<string, mixed>>|null $pathSchema */
        $pathSchema = RequestMessageSchemaFactory::filterParameters($schema, $allParameterNames);

        if ($pathSchema === null && ! empty($allParameterNames)) {
            throw new RuntimeException(
                sprintf(
                    'The uri parameter names are not present in the message schema for message \'%s\'.',
                    $messageClass,
                ),
            );
        }

        if ($pathSchema === null) {
            return null;
        }

        return array_map(
            function (string $parameterName) use ($pathSchema, $pathParameterNames, $messageClass) {
                /** @var array<string, mixed> $propertySchema */
                $propertySchema = $pathSchema['properties'][$parameterName];

                if (ComplexTypeExtractor::complexTypeWanted()) {
                    $types = $this->propertyInfoExtractor->getTypes($messageClass, $parameterName) ?? [];
                    /** @var Type|null $type */
                    $type = empty($types) ? null : reset($types);

                    if (ComplexTypeExtractor::isClassComplexType($type?->getClassName())) {
                        $propertySchema['type'] = ComplexTypeExtractor::complexType($type?->getClassName());
                    }
                }

                $openApiSchema = OpenApiSchemaFactory::toOpenApiSchema($propertySchema);
                /** @var string $description */
                $description = $openApiSchema['description'] ?? self::typeDescription(
                    $messageClass,
                    $parameterName,
                    $this->docBlockFactory,
                ) ?? '';
                $deprecated = $openApiSchema['deprecated'] ?? false;
                $example = $openApiSchema['example'] ?? null;

                return new Parameter(
                    name: $parameterName,
                    in: in_array($parameterName, $pathParameterNames) ? 'path' : 'query',
                    description: $description,
                    required: in_array($parameterName, $pathSchema['required'] ?? []),
                    deprecated: $deprecated,
                    schema: $openApiSchema,
                    example: $example,
                );
            },
            $allParameterNames,
        );
    }

    /** @param class-string<ImmutableRecord> $messageClass */
    public static function typeDescription(
        string $messageClass,
        string $property,
        DocBlockFactory $docBlockFactory,
    ): string|null {
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
                    $docBlock->getDescription()->render(),
                );
            } catch (InvalidArgumentException) {
            }
        }

        return null;
    }

    /** @param ReflectionClass<ApiPlatformMessage> $messageReflectionClass */
    public function defaultStatusCode(ReflectionClass $messageReflectionClass): int|null
    {
        return $this->responseExtractor->hasResponsesFromReflectionClass($messageReflectionClass)
            ? $this->responseExtractor->defaultStatusCodeFromReflectionClass($messageReflectionClass)
            : null;
    }
}
