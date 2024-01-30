<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CacheMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CallbackMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Processor\CommandProcessor;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreCollectionProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreItemProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory\MessageTypeFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Message\ValidationMessage;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\CommandExtractor;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\QueryExtractor;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\ResponseExtractor;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

use function array_key_exists;
use function array_map;
use function class_exists;
use function class_implements;
use function in_array;
use function ltrim;
use function reset;
use function sprintf;
use function strtolower;
use function ucfirst;

final class EventEngineMessageResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private readonly DocBlockFactory $docBlockFactory;

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly Config $config,
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly CommandExtractor $commandExtractor,
        private readonly QueryExtractor $queryExtractor,
        private readonly ResponseExtractor $responseExtractor,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);
        $messageMapping = $this->config->messageMapping();

        // if no event engine messages are found linked with this resource.
        if (! array_key_exists($resourceClass, $messageMapping)) {
            return $resourceMetadataCollection;
        }

        /** @var array<class-string<ApiPlatformMessage>> $messages */
        $messages = $messageMapping[$resourceClass];
        $operations = [];

        foreach ($messages as $messageClass) {
            $messageReflectionClass = new ReflectionClass($messageClass);
            $operationClass = $this->operationClass($messageClass);
            $docBlock = $this->docBlock($messageClass);
            /** @var array<class-string> $messageInterfaces */
            $messageInterfaces = class_implements($messageClass) ?: [];

            $operation = (new $operationClass(
                name: $messageClass::__operationId(),
                shortName: $messageClass::__schemaStateClass()::__type(),
                description: $docBlock?->getSummary(),
                deprecationReason: $this->deprecationReason($messageClass),
                class: $resourceClass,
                uriTemplate: '/' . ltrim(Uri::fromString($messageClass::__uriTemplate())->toUrlPart(), '/'),
                uriVariables: null, //todo
                requirements: $messageClass::__requirements(),
                read: $this->queryExtractor->isQueryFromReflectionClass($messageReflectionClass),
                write: $this->commandExtractor->isCommandFromReflectionClass($messageReflectionClass),
                serialize: null, // todo
                validate: in_array(ValidationMessage::class, $messageInterfaces),
                status: $this->defaultStatusCode($messageReflectionClass),
                normalizationContext: $messageClass::__normalizationContext(),
                denormalizationContext: $messageClass::__denormalizationContext(),
                openapi: $this->openApi($messageClass, $messageInterfaces),
                processor: $this->processor($messageReflectionClass),
                provider: $this->provider($messageReflectionClass),
                input: ['class' => $messageClass],
                output: $resourceClass !== $messageClass::__schemaStateClass()
                    ? ['class' => $messageClass::__schemaStateClass()]
                    : null,
                cacheHeaders: in_array(CacheMessage::class, $messageInterfaces)
                    ? [
                        'max_age' => $messageClass::__maxAge(),
                        'shared_max_age' => $messageClass::__sharedMaxAge(),
                        'vary' => $messageClass::__vary(),
                    ]
                    : null,
            ))
                ->withMethod($messageClass::__httpMethod());

            if ($operation instanceof Put) {
                $operation = $operation->withExtraProperties(['standard_put' => true]);
            }

            $operations[$messageClass::__operationId()] = $operation;
        }

        foreach ($resourceMetadataCollection as $resourceMetadata) {
            $resourceMetadata = $resourceMetadata
                ->withShortName($resourceClass::__type())
                ->withOperations(new Operations($operations));
        }

        return $resourceMetadataCollection;
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     *
     * @return class-string<HttpOperation>
     */
    private function operationClass(string $messageClass): string
    {
        /** @var class-string<HttpOperation> $operationClass */
        $operationClass = sprintf(
            'ApiPlatform\Metadata\%s%s',
            ucfirst(strtolower($messageClass::__httpMethod())),
            $messageClass::__isCollection() && $messageClass::__httpMethod() === Request::METHOD_GET
                ? 'Collection'
                : '',
        );

        return class_exists($operationClass)
            ? $operationClass
            : HttpOperation::class;
    }

    /** @param class-string<ApiPlatformMessage> $messageClass */
    private function docBlock(string $messageClass): DocBlock|null
    {
        $reflectionClass = new ReflectionClass($messageClass);
        try {
            return $this->docBlockFactory->create($reflectionClass);
        } catch (InvalidArgumentException) {
        }

        return null;
    }

    /** @param class-string<ApiPlatformMessage> $messageClass */
    private function deprecationReason(string $messageClass): string|null
    {
        $reflectionClass = new ReflectionClass($messageClass);

        $deprecations = $reflectionClass->getAttributes(Deprecated::class);
        $deprecation = reset($deprecations);

        if (! $deprecation) {
            return null;
        }

        return $deprecation->getArguments()[0] ?? 'Deprecated';
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param array<class-string> $messageInterfaces
     */
    private function openApi(string $messageClass, array $messageInterfaces): OpenApiOperation
    {
        $docBlock = $this->docBlock($messageClass);

        return new OpenApiOperation(
            operationId: $messageClass::__operationId(),
            tags: $messageClass::__tags(),
            summary: $docBlock?->getSummary() ?? '',
            description: $docBlock?->getDescription()->render() ?? '',
            callbacks: $this->buildCallbacks($messageClass, $messageInterfaces),
            parameters: $this->parameters($messageClass),
        );
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param array<class-string> $messageInterfaces
     *
     * @return ArrayObject<string, mixed>|null
     */
    private function buildCallbacks(string $messageClass, array $messageInterfaces): ArrayObject|null
    {
        if (! in_array(CallbackMessage::class, $messageInterfaces)) {
            return null;
        }

        /** @var array<string, class-string<JsonSchemaAwareRecord>> $events */
        $events = $messageClass::__callbackEvents();

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
        $pathSchema = MessageSchemaFactory::filterParameters($schema, $allParameterNames);

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

                if (isset($_GET['complex']) || isset($_SERVER['complex'])) {
                    $types = $this->propertyInfoExtractor->getTypes($messageClass, $parameterName) ?? [];
                    /** @var Type|null $type */
                    $type = empty($types) ? null : reset($types);

                    if (MessageTypeFactory::isComplexType($type?->getClassName())) {
                        $propertySchema['type'] = MessageTypeFactory::complexType($type?->getClassName());
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

    /**
     * @param ReflectionClass<ApiPlatformMessage> $messageReflectionClass
     *
     * @return class-string<ProcessorInterface<mixed, mixed>>|null
     */
    private function processor(ReflectionClass $messageReflectionClass): string|null
    {
        if (! $this->commandExtractor->isCommandFromReflectionClass($messageReflectionClass)) {
            return null;
        }

        /** @var ApiPlatformMessage $messageClass */
        $messageClass = $messageReflectionClass->getName();

        return $messageClass::__processor() ?? CommandProcessor::class;
    }

    /**
     * @param ReflectionClass<ApiPlatformMessage> $messageReflectionClass
     *
     * @return class-string<ProviderInterface<ImmutableRecord>>|null
     */
    private function provider(ReflectionClass $messageReflectionClass): string|null
    {
        if (! $this->queryExtractor->isQueryFromReflectionClass($messageReflectionClass)) {
            return null;
        }

        /** @var ApiPlatformMessage $messageClass */
        $messageClass = $messageReflectionClass->getName();

        return $messageClass::__isCollection()
            ? DocumentStoreCollectionProvider::class
            : DocumentStoreItemProvider::class;
    }

    /** @param ReflectionClass<ApiPlatformMessage> $messageReflectionClass */
    public function defaultStatusCode(ReflectionClass $messageReflectionClass): int|null
    {
        return $this->responseExtractor->hasResponsesFromReflectionClass($messageReflectionClass)
            ? $this->responseExtractor->defaultStatusCodeFromReflectionClass($messageReflectionClass)
            : null;
    }
}
