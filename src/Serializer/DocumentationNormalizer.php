<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\OpenApiSchemaFactoryInterface;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\DocumentationException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\AuthorizationMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\Bundle\EventEngineBundle\Type\DefaultType;
use ADS\Bundle\EventEngineBundle\Util\StringUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Swagger\Serializer\DocumentationNormalizer as SwaggerDocumentationNormalizer;
use EventEngine\EventEngine;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function array_diff;
use function array_diff_key;
use function array_filter;
use function array_flip;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_search;
use function array_values;
use function count;
use function explode;
use function in_array;
use function is_array;
use function ksort;
use function lcfirst;
use function mb_strtolower;
use function method_exists;
use function reset;
use function str_contains;
use function str_replace;
use function strtolower;
use function strtoupper;
use function substr;
use function ucfirst;
use function uksort;

final class DocumentationNormalizer implements NormalizerInterface
{
    private const METHOD_SORT = [
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_GET,
    ];

    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private OperationPathResolverInterface $operationPathResolver;
    private EventEngine $eventEngine;
    private Config $config;
    private OpenApiSchemaFactoryInterface $schemaFactory;

    public function __construct(
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        OperationPathResolverInterface $operationPathResolver,
        EventEngine $eventEngine,
        Config $config,
        OpenApiSchemaFactoryInterface $schemaFactory
    ) {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->operationPathResolver = $operationPathResolver;
        $this->eventEngine = $eventEngine;
        $this->config = $config;
        $this->schemaFactory = $schemaFactory;
    }

    /**
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        [$tags, $messages, $components] = $this->messages($object);
        $components[ApiPlatformException::REF] = ApiPlatformException::__schema()->toArray();

        $paths = $this->paths($messages);
        $components = $this->components($components);

        return $this->buildSchema($paths, $tags, $components);
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $format === SwaggerDocumentationNormalizer::FORMAT && $data instanceof Documentation;
    }

    /**
     * @return array<mixed>
     */
    private function messages(Documentation $documentation): array
    {
        $tags = [];
        $messages = [];
        $components = [];

        foreach ($documentation->getResourceNameCollection() as $resourceClass) {
            /** @var class-string $resourceClass */
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
            $reflectionClass = new ReflectionClass($resourceClass);

            $tags[$resourceMetadata->getShortName()] = $resourceMetadata->getDescription();

            if ($reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
                $components[$resourceMetadata->getShortName()] = $resourceClass::__schema()->toArray();
            }

            $messages = array_merge($messages, $this->resourceMessages($resourceClass, $resourceMetadata));
        }

        return [$tags, $messages, $components];
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<class-string, mixed>
     */
    private function resourceMessages(string $resourceClass, ResourceMetadata $resourceMetadata): array
    {
        $messages = [];

        $messageMapping = $this->config->messageMapping();

        foreach ($resourceMetadata->getItemOperations() ?? [] as $itemOperationName => $itemOperation) {
            /** @var class-string|null $messageClass */
            $messageClass = $messageMapping[$resourceClass][OperationType::ITEM][$itemOperationName] ?? null;

            if (! $messageClass) {
                continue;
            }

            $messages[$messageClass] = $itemOperation;
        }

        foreach ($resourceMetadata->getCollectionOperations() ?? [] as $collectionOperationName => $collectionOperation) {
            /** @var class-string|null $messageClass */
            $messageClass = $messageMapping[$resourceClass][OperationType::COLLECTION][$collectionOperationName] ?? null;

            if (! $messageClass) {
                continue;
            }

            $messages[$messageClass] = $collectionOperation;
        }

        return $messages;
    }

    /**
     * @param array<class-string, mixed> $messages
     *
     * @return array<mixed>
     */
    private function paths(array $messages): array
    {
        $paths = [];

        $schemas = $this->schemas($messages);

        $operationMapping = $this->config->operationMapping();

        foreach ($messages as $messageClass => $operation) {
            if (! isset($operationMapping[$messageClass])) {
                throw ApiPlatformMappingException::noOperationFound($messageClass);
            }

            $operation = array_merge(
                $operationMapping[$messageClass],
                $operation
            );

            try {
                $shortName = $this->resourceMetadataFactory->create($operation['resource'])->getShortName();
            } catch (ResourceClassNotFoundException $exception) {
                $shortName = (new ReflectionClass($operation['resource']))->getShortName();
            }

            $operation['resourceShortName'] = $shortName;
            $schema = $schemas[$messageClass];
            $method = strtolower($operation['method']);
            $path = $this->path($operation);
            $operation = $this->operation($path, $schema, $operation, $messageClass);

            if (! isset($paths[$path->toUrlPart()])) {
                $paths[$path->toUrlPart()] = [];
            }

            $paths[$path->toUrlPart()][$method] = $operation;
        }

        ksort($paths);

        return array_map(
            static function (array $methods) {
                uksort(
                    $methods,
                    static function (string $methodA, string $methodB) {
                        return (int) array_search(strtoupper($methodA), self::METHOD_SORT)
                            - (int) array_search(strtoupper($methodB), self::METHOD_SORT);
                    }
                );

                return $methods;
            },
            $paths
        );
    }

    /**
     * @param array<mixed> $operation
     */
    private function path(array $operation): Uri
    {
        // @phpstan-ignore-next-line
        $path = $this->operationPathResolver->resolveOperationPath(
            $operation['resourceShortName'],
            $operation,
            $operation['operationType'],
            $operation['operationName']
        );

        if (str_contains($operation['path'] ?? '', '?')) {
            $parts = explode('?', $operation['path'], 2);
            $path .= '?' . $parts[1];
        }

        if (substr($path, -10) === '.{_format}') {
            $path = substr($path, 0, -10);
        }

        return Uri::fromString($path);
    }

    /**
     * @param array<mixed> $schema
     * @param array<string, mixed> $operation
     * @param class-string $messageClass
     *
     * @return array<string, mixed>
     */
    private function operation(Uri $path, array $schema, array $operation, string $messageClass): array
    {
        $method = $operation['method'];
        $reflectionClass = new ReflectionClass($messageClass);
        $operation = array_filter([
            'summary' => $operation['openapi_context']['summary'] ?? null,
            'description' => $operation['openapi_context']['description'] ?? null,
            'tags' => $operation['tags'] ?? [$operation['resourceShortName']],
            'operationId' => $operation['operationId']
                ?? lcfirst($operation['operationName'])
                . ucfirst($operation['resourceShortName'])
                . ucfirst($operation['operationType']),
            'parameters' => array_merge(
                $this->pathParameters($path, $schema),
                $this->queryParameters($path, $schema)
            ),
            'responses' => $this->responses($messageClass, $reflectionClass, $method, $schema),
        ]);

        if ($schema !== null || $method === Request::METHOD_POST) {
            if (
                $schema
                && method_exists($messageClass, '__requestBodyArrayProperty')
                && $messageClass::__requestBodyArrayProperty()
            ) {
                $schema = $schema['properties'][$messageClass::__requestBodyArrayProperty()];
            }

            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    $method === Request::METHOD_PATCH
                        ? 'application/merge-patch+json'
                        : 'application/json' => [
                            'schema' => self::convertSchema($schema ?? JsonSchema::object([])->toArray()),
                        ],
                ],
            ];
        }

        return $operation;
    }

    /**
     * @param array<mixed> $schema
     *
     * @return array<int, array<string, mixed>>
     */
    public function pathParameters(Uri $path, ?array &$schema): array
    {
        if ($schema === null) {
            return [];
        }

        $pathParameterNames = array_map([StringUtil::class, 'camelize'], $path->toPathParameterNames());

        $pathParameters = array_map(
            static function (string $parameterName) use ($schema) {
                return [
                    'name' => StringUtil::decamilize($parameterName),
                    'schema' => self::convertSchema($schema['properties'][$parameterName]),
                    'required' => in_array($parameterName, $schema['required']),
                    'in' => 'path',
                ];
            },
            $pathParameterNames
        );

        $schema = self::removeFromSchema($schema, $pathParameterNames);

        return $pathParameters;
    }

    /**
     * @param array<mixed> $schema
     *
     * @return array<int, array<string, mixed>>
     */
    public function queryParameters(Uri $path, ?array &$schema): array
    {
        if ($schema === null) {
            return [];
        }

        $queryParameterNames = array_map([StringUtil::class, 'camelize'], $path->toQueryParameterNames());

        $queryParameters = array_map(
            static function (string $parameterName) use ($schema) {
                return [
                    'name' => StringUtil::decamilize($parameterName),
                    'schema' => self::convertSchema($schema['properties'][$parameterName]),
                    'required' => in_array($parameterName, $schema['required']),
                    'in' => 'query',
                ];
            },
            $queryParameterNames
        );

        $schema = self::removeFromSchema($schema, $queryParameterNames);

        return $queryParameters;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @param array<mixed> $schema
     *
     * @return array<array<string, mixed>>|null
     */
    public function responses(
        string $messageClass,
        ReflectionClass $reflectionClass,
        string $method,
        ?array $schema = null
    ): ?array {
        if (! $reflectionClass->implementsInterface(HasResponses::class)) {
            return null;
        }

        return array_map(
            static function (TypeSchema $response) {
                return [
                    'description' => $response instanceof AnnotatedType
                        ? $response->toArray()['description'] ?? ''
                        : '',
                    'content' => [
                        'application/json' => [
                            'schema' => self::convertSchema($response->toArray()),
                        ],
                    ],
                ];
            },
            array_filter(
                $messageClass::__responseSchemasPerStatusCode() +
                [
                    Response::HTTP_BAD_REQUEST => $schema === null ? null : ApiPlatformException::badRequest(),
                    Response::HTTP_UNAUTHORIZED => $reflectionClass
                        ->implementsInterface(AuthorizationMessage::class)
                        ? ApiPlatformException::unauthorized()
                        : null,
                    Response::HTTP_FORBIDDEN => $reflectionClass
                        ->implementsInterface(AuthorizationMessage::class)
                        ? ApiPlatformException::forbidden()
                        : null,
                    Response::HTTP_NO_CONTENT => $method === Request::METHOD_DELETE
                        ? DefaultType::emptyResponse()
                        : null,
                    Response::HTTP_CREATED => $method === Request::METHOD_POST
                        ? DefaultType::created()
                        : null,
                    Response::HTTP_OK => in_array(
                        $method,
                        [
                            Request::METHOD_PUT,
                            Request::METHOD_PATCH,
                        ]
                    )
                        ? DefaultType::ok()
                        : null,
                ]
            ),
        );
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $parameters
     *
     * @return array<mixed>|null
     */
    private static function removeFromSchema(array $schema, array $parameters): ?array
    {
        $schema['properties'] ??= [];

        $filteredSchema = $schema;
        $filteredSchema['properties'] = array_diff_key($schema['properties'], array_flip($parameters));

        if (empty($filteredSchema['properties'])) {
            return null;
        }

        $filteredSchema['required'] = array_values(array_diff($schema['required'], $parameters));

        return $filteredSchema;
    }

    /**
     * @param array<class-string, mixed> $messages
     *
     * @return array<string, array<mixed>>
     */
    private function schemas(array $messages): array
    {
        $schemas = [];

        foreach ($messages as $messageClass => $operation) {
            $reflectionClass = new ReflectionClass($messageClass);

            if ($reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
                $schemas[$messageClass] = $messageClass::__schema()->toArray();

                continue;
            }

            if ($reflectionClass->implementsInterface(JsonSchemaAwareCollection::class)) {
                $schemas[$messageClass] = JsonSchema::array($messageClass::__itemSchema())->toArray();

                continue;
            }
        }

        return $schemas;
    }

    /**
     * @param array<string, array<mixed>>  $components
     *
     * @return array<mixed>
     */
    private function components(array $components): array
    {
        return array_map(
            [self::class, 'convertSchema'],
            array_merge(
                $this->eventEngine->compileCacheableConfig()['responseTypes'],
                $components
            )
        );
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function convertSchema(array $jsonSchema): array
    {
        if (isset($jsonSchema['type']) && is_array($jsonSchema['type'])) {
            $type = null;
            foreach ($jsonSchema['type'] as $possibleType) {
                if (mb_strtolower($possibleType) !== 'null') {
                    if ($type) {
                        throw DocumentationException::moreThanOneNullType($jsonSchema);
                    }

                    $type = $possibleType;
                } else {
                    $jsonSchema['nullable'] = true;
                }
            }

            $jsonSchema['type'] = $type;
        }

        if (isset($jsonSchema['properties']) && is_array($jsonSchema['properties'])) {
            foreach ($jsonSchema['properties'] as $propName => $propSchema) {
                $decamilize = StringUtil::decamilize($propName);
                $jsonSchema['properties'][$decamilize] = self::convertSchema($propSchema);

                if ($decamilize === $propName) {
                    continue;
                }

                unset($jsonSchema['properties'][$propName]);
            }
        }

        if (isset($jsonSchema['oneOf']) && is_array($jsonSchema['oneOf'])) {
//            $key = array_search('null', $jsonSchema['oneOf']);
//            if ($key !== false) {
//                $jsonSchema['nullable'] = true;
//
//                unset($jsonSchema['oneOf'][$key]);
//            }

            foreach ($jsonSchema['oneOf'] as $oneOfName => $oneOfSchema) {
                $jsonSchema['oneOf'][$oneOfName] = self::convertSchema($oneOfSchema);
            }
        }

        if (isset($jsonSchema['items']) && is_array($jsonSchema['items'])) {
            $jsonSchema['items'] = self::convertSchema($jsonSchema['items']);
        }

        if (isset($jsonSchema['$ref'])) {
            $jsonSchema['$ref'] = str_replace('definitions', 'components/schemas', $jsonSchema['$ref']);
        }

        if (
            isset($jsonSchema['enum'], $jsonSchema['type'])
            && $jsonSchema['type'] === 'string'
            && in_array(null, $jsonSchema['enum'])
        ) {
            $jsonSchema['enum'] = array_filter($jsonSchema['enum']);
        }

        if (isset($jsonSchema['examples'])) {
            $jsonSchema['example'] = reset($jsonSchema['examples']);

            unset($jsonSchema['examples']);
        }

        if (isset($jsonSchema['required']) && count($jsonSchema['required']) === 0) {
            unset($jsonSchema['required']);
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $paths
     * @param array<string, string> $tags
     * @param array<mixed> $components
     *
     * @return array<mixed>
     */
    private function buildSchema(array $paths, array $tags, array $components): array
    {
        $tagNamesToHide = $this->schemaFactory->hideTags();

        $tags = array_filter(
            $this->schemaFactory->createTags($tags),
            static function (array $tag) use ($tagNamesToHide) {
                return ! in_array($tag['name'], $tagNamesToHide);
            }
        );

        $paths = array_filter(
            array_map(
                static function (array $operations) use ($tagNamesToHide) {
                    $operations = array_filter(
                        $operations,
                        static function (array $operation) use ($tagNamesToHide) {
                            foreach ($tagNamesToHide as $tagNameToHide) {
                                if (in_array($tagNameToHide, $operation['tags'])) {
                                    return false;
                                }
                            }

                            return true;
                        }
                    );

                    if (empty($operations)) {
                        return null;
                    }

                    return $operations;
                },
                $paths
            )
        );

        return array_merge_recursive(
            $this->schemaFactory->create(),
            [
                'paths' => $paths,
                'tags' => array_values($tags),
                'components' => ['schemas' => $components],
            ]
        );
    }
}
