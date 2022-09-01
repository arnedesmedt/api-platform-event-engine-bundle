<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CallbackMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\EmptyObject;
use ADS\Bundle\ApiPlatformEventEngineBundle\Processor\CommandProcessor;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreCollectionProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreItemProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\OpenApiSchemaFactory;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\Util\ArrayUtil;
use ADS\Util\StringUtil;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function class_exists;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_quote;
use function reset;
use function sprintf;
use function strtolower;
use function ucfirst;

final class MessageResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private Config $config,
        private PropertyInfoExtractorInterface $propertyInfoExtractor,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);
        $messageMapping = $this->config->messageMapping();

        if (! array_key_exists($resourceClass, $messageMapping)) {
            return $resourceMetadataCollection;
        }

        /** @var array<class-string<ApiPlatformMessage>> $messages */
        $messages = $messageMapping[$resourceClass];
        $resourceMetadataCollection->getIterator()->rewind();
        /** @var ApiResource $resource */
        $resource = $resourceMetadataCollection->getIterator()->current();

        $operations = array_map(
            function (string $messageClass) use ($resourceClass, $resource) {
                $reflectionClass = new ReflectionClass($messageClass);
                try {
                    $docBlock = $this->docBlockFactory->create($reflectionClass);
                } catch (InvalidArgumentException) {
                    $docBlock = null;
                }

                /** @var class-string<HttpOperation> $operationClass */
                $operationClass = sprintf(
                    'ApiPlatform\Metadata\%s%s',
                    ucfirst(strtolower($messageClass::__httpMethod())),
                    $messageClass::__isCollection() && $messageClass::__httpMethod() === Request::METHOD_GET
                        ? 'Collection'
                        : ''
                );

                $method = $messageClass::__httpMethod();

                if (! class_exists($operationClass)) {
                    $operationClass = HttpOperation::class;
                }

                return (new $operationClass(
                    name: $messageClass::__operationId(),
                    shortName: $messageClass::__schemaStateClass()::__type(),
                    description: $docBlock?->getSummary(),
                    class: $resourceClass,
                    uriTemplate: '/' . ltrim(Uri::fromString($messageClass::__uriTemplate())->toUrlPart(), '/'),
                    uriVariables: $this->linksFromUriTemplate($messageClass::__uriTemplate(), $resourceClass),
                    read: ! $reflectionClass->implementsInterface(Command::class),
                    stateless: $messageClass::__stateless(),
                    status: $reflectionClass->implementsInterface(HasResponses::class)
                        ? $messageClass::__defaultStatusCode()
                        : null,
                    input: $messageClass::__inputClass() ?? $messageClass,
                    output: $messageClass::__outputClass() ?? (
                        $reflectionClass->implementsInterface(Query::class)
                            ? $messageClass::__schemaStateClass()
                            : EmptyObject::class
                    ),
                    deprecationReason: $this->deprecationReason($reflectionClass),
                    openapiContext: array_filter(
                        [
                            'operationId' => $messageClass::__operationId(),
                            'tags' => $messageClass::__tags(),
                            'summary' => $docBlock?->getSummary() ?? '',
                            'description' => $docBlock?->getDescription()->render() ?? '',
                            'callbacks' => $this->buildCallbacks($messageClass, $reflectionClass),
                            'parameters' => $this->parameters($messageClass),
                            'x-message-class' => $messageClass,
                            'x-resource-class' => $resourceClass,
                            'x-operation-name' => $messageClass::__operationName(),
                        ],
                        static fn ($value) => $value !== null
                    ),
                        normalizationContext: $resource->getNormalizationContext(),
                        denormalizationContext: $resource->getDenormalizationContext(),
                        processor: $messageClass::__processor() ?? (
                        $reflectionClass->implementsInterface(Command::class)
                            ? CommandProcessor::class
                            : null
                    ),
                    provider: $reflectionClass->implementsInterface(Query::class)
                        ? (
                            $messageClass::__isCollection()
                                ? DocumentStoreCollectionProvider::class
                                : DocumentStoreItemProvider::class
                        )
                        : null
                ))
                    ->withMethod($method);
            },
            $messages
        );

        $resourceMetadataCollection->offsetSet(
            $resourceMetadataCollection->getIterator()->key(),
            $resource->withOperations(new Operations($operations))
        );

        return $resourceMetadataCollection;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return array<string, mixed>|null
     */
    private function buildCallbacks(string $messageClass, ReflectionClass $reflectionClass): ?array
    {
        if (! $reflectionClass->implementsInterface(CallbackMessage::class)) {
            return null;
        }

        /** @var array<string, class-string<JsonSchemaAwareRecord>> $events */
        $events = $messageClass::__callbackEvents();

        return array_map(
            static function (string $schemaClass) {
                return [
                    '{$request.body#/callback_url}' => [
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
     * @param ReflectionClass<object> $reflectionClass
     */
    private function deprecationReason(ReflectionClass $reflectionClass): ?string
    {
        $deprecations = $reflectionClass->getAttributes(Deprecated::class);
        $deprecation = reset($deprecations);

        if (! $deprecation) {
            return null;
        }

        return $deprecation->getArguments()[0] ?? 'Deprecated';
    }

    /**
     * @param class-string<ApiPlatformMessage> $messageClass
     *
     * @return array<array<string, mixed>>
     */
    private function parameters(
        string $messageClass
    ): ?array {
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
                    $messageClass
                )
            );
        }

        if ($pathSchema === null) {
            return null;
        }

        return array_map(
            function (string $parameterName) use ($pathSchema, $pathParameterNames, $messageClass) {
                /** @var array<string, mixed> $propertySchema */
                $propertySchema = $pathSchema['properties'][$parameterName];

                /** @var Type|null $type */
                $type = null;
                if (isset($_GET['complex'])) {
                    $types = $this->propertyInfoExtractor->getTypes($messageClass, $parameterName) ?? [];
                    $type = reset($types);
                }

                if (
                    $type
                    && $type->getClassName()
                    && preg_match(
                        sprintf('#%s#', preg_quote($_GET['complex'], '#')),
                        $type->getClassName()
                    )
                ) {
                    $propertySchema['type'] = $type->getClassName();
                }

                $openApiSchema = OpenApiSchemaFactory::toOpenApiSchema($propertySchema);

                return [
                    'name' => StringUtil::decamelize($parameterName),
                    'in' => in_array($parameterName, $pathParameterNames) ? 'path' : 'query',
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
            },
            $allParameterNames
        );
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

    /**
     * @return array<string, Link>
     */
    private function linksFromUriTemplate(string $uriTemplate, string $resourceClass): array
    {
        $uri = Uri::fromString($uriTemplate);
        /** @var array<string> $parameterNames */
        $parameterNames = ArrayUtil::toSnakeCasedValues($uri->toPathParameterNames());

        return array_combine(
            $parameterNames,
            array_map(
                static fn (string $parameterName) => (new Link())
                    ->withParameterName($parameterName)
                    ->withFromClass($resourceClass)
                    ->withIdentifiers([StringUtil::camelize($parameterName)]),
                $parameterNames
            )
        );
    }
}
