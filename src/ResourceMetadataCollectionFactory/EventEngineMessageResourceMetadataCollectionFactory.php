<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CallbackMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Processor\CommandProcessor;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreCollectionProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreItemProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory\MessageTypeFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Message\ValidationMessage;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
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

use function array_filter;
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
        private readonly Config $config,
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = new ResourceMetadataCollection($resourceClass);
        $messageMapping = $this->config->messageMapping();

        // if no event engine messages are found linked with this resource.
        if (! array_key_exists($resourceClass, $messageMapping)) {
            return $resourceMetadataCollection;
        }

        /** @var array<class-string<ApiPlatformMessage>> $messages */
        $messages = $messageMapping[$resourceClass];
        $operations = [];

        foreach ($messages as $messageClass) {
            $operationClass = $this->operationClass($messageClass);
            $docBlock = $this->docBlock($messageClass);
            /** @var array<class-string> $messageInterfaces */
            $messageInterfaces = class_implements($messageClass) ?: [];

            $operations[$messageClass::__operationId()] = (new $operationClass(
                name: $messageClass::__operationId(),
                shortName: $messageClass::__schemaStateClass()::__type(),
                description: $docBlock?->getSummary(),
                deprecationReason: $this->deprecationReason($messageClass),
                class: $resourceClass,
                uriTemplate: '/' . ltrim(Uri::fromString($messageClass::__uriTemplate())->toUrlPart(), '/'),
                uriVariables: null, //todo
                requirements: $messageClass::__requirements(),
                read: in_array(Query::class, $messageInterfaces),
                write: in_array(Command::class, $messageInterfaces),
                serialize: null, // todo
                validate: in_array(ValidationMessage::class, $messageInterfaces),
                status: in_array(HasResponses::class, $messageInterfaces)
                    ? $messageClass::__defaultStatusCode()
                    : null,
                normalizationContext: $messageClass::__normalizationContext(),
                denormalizationContext: $messageClass::__denormalizationContext(),
                openapiContext: $this->openApiContext($messageClass, $messageInterfaces),
                processor: $this->processor($messageClass, $messageInterfaces),
                provider: $this->provider($messageClass, $messageInterfaces),
                input: ['class' => $messageClass],
                output: $resourceClass !== $messageClass::__schemaStateClass()
                    ? ['class' => $messageClass::__schemaStateClass()]
                    : null,
            ))
                ->withMethod($messageClass::__httpMethod());
        }

        $resourceMetadataCollection[] = (new ApiResource(class: $resourceClass))
            ->withShortName($resourceClass::__type())
            ->withOperations(new Operations($operations));

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
     *
     * @return array<string, mixed>
     */
    private function openApiContext(string $messageClass, array $messageInterfaces): array
    {
        $docBlock = $this->docBlock($messageClass);

        return array_filter(
            [
                'operationId' => $messageClass::__operationId(),
                'tags' => $messageClass::__tags(),
                'summary' => $docBlock?->getSummary() ?? '',
                'description' => $docBlock?->getDescription()->render() ?? '',
                'callbacks' => $this->buildCallbacks($messageClass, $messageInterfaces),
                'parameters' => $this->parameters($messageClass),
            ],
            static fn ($value) => $value !== null,
        );
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param array<class-string> $messageInterfaces
     *
     * @return array<mixed>|null
     */
    private function buildCallbacks(string $messageClass, array $messageInterfaces): array|null
    {
        if (! in_array(CallbackMessage::class, $messageInterfaces)) {
            return null;
        }

        /** @var array<string, class-string<JsonSchemaAwareRecord>> $events */
        $events = $messageClass::__callbackEvents();

        return array_map(
            /** @param class-string<JsonSchemaAwareRecord> $schemaClass */
            static function (string $schemaClass) {
                return [
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
                                '200' => ['description' => 'Your server return a 200 OK, if it accpets the callback.'],
                            ],
                        ],
                    ],
                ];
            },
            $events,
        );
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     *
     * @return array<array<string, mixed>>
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

                if (isset($_GET['complex'])) {
                    $types = $this->propertyInfoExtractor->getTypes($messageClass, $parameterName) ?? [];
                    /** @var Type|null $type */
                    $type = empty($types) ? null : reset($types);

                    if (MessageTypeFactory::isComplexType($type?->getClassName())) {
                        $propertySchema['type'] = MessageTypeFactory::complexType($type?->getClassName());
                    }
                }

                $openApiSchema = OpenApiSchemaFactory::toOpenApiSchema($propertySchema);

                return [
                    'name' => $parameterName,
                    'in' => in_array($parameterName, $pathParameterNames) ? 'path' : 'query',
                    'schema' => $openApiSchema,
                    'required' => in_array($parameterName, $pathSchema['required'] ?? []),
                    'description' => $openApiSchema['description'] ?? self::typeDescription(
                        $messageClass,
                        $parameterName,
                        $this->docBlockFactory,
                    ),
                    'deprecated' => $openApiSchema['deprecated'] ?? false,
                    'example' => $openApiSchema['example'] ?? null,
                ];
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
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param array<class-string> $messageInterfaces
     *
     * @return class-string<ProcessorInterface>|null
     */
    private function processor(string $messageClass, array $messageInterfaces): string|null
    {
        if (! in_array(Command::class, $messageInterfaces)) {
            return null;
        }

        return $messageClass::__processor() ?? CommandProcessor::class;
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     * @param array<class-string> $messageInterfaces
     *
     * @return class-string<ProviderInterface<object>>|null
     */
    private function provider(string $messageClass, array $messageInterfaces): string|null
    {
        if (! in_array(Query::class, $messageInterfaces)) {
            return null;
        }

        return $messageClass::__isCollection()
            ? DocumentStoreCollectionProvider::class
            : DocumentStoreItemProvider::class;
    }
}
