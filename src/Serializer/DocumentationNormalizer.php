<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource\ChangeApiResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\OpenApiSchemaFactoryInterface;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\DocumentationException;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\Message\HasResponses;
use ADS\Bundle\EventEngineBundle\Util\StringUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Swagger\Serializer\DocumentationNormalizer as SwaggerDocumentationNormalizer;
use EventEngine\EventEngine;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use function array_diff;
use function array_diff_key;
use function array_filter;
use function array_flip;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function lcfirst;
use function mb_strtolower;
use function reset;
use function str_replace;
use function strtolower;
use function substr;
use function ucfirst;

final class DocumentationNormalizer implements NormalizerInterface
{
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private OperationPathResolverInterface $operationPathResolver;
    private EventEngine $eventEngine;
    /** @var array<string, array<string, array<string, string>>> */
    private array $messageMapping;
    /** @var array<string, array<string, string>> */
    private array $operationMapping;
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
        $this->messageMapping = $config->messageMapping();
        $this->operationMapping = $config->operationMapping();
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
        [$tags, $messages] = $this->messages($object);

        $paths = $this->paths($messages);
        $components = $this->components();

        return $this->buildSchema($paths, $tags, $components);
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null) : bool
    {
        return $format === SwaggerDocumentationNormalizer::FORMAT && $data instanceof Documentation;
    }

    /**
     * @return array<mixed>
     */
    private function messages(Documentation $documentation) : array
    {
        $tags = [];
        $messages = [];
        foreach ($documentation->getResourceNameCollection() as $resourceClass) {
            /** @var class-string $resourceClass */
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

            $reflectionClass = new ReflectionClass($resourceClass);

            $resourceClass = $reflectionClass->implementsInterface(ChangeApiResource::class)
                ? $resourceClass::__newApiResource()
                : $resourceClass;

            $tags[$resourceMetadata->getShortName()] = $resourceMetadata->getDescription();
            $messages = array_merge($messages, $this->resourceMessages($resourceClass, $resourceMetadata));
        }

        return [$tags, $messages];
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<class-string, mixed>
     */
    private function resourceMessages(string $resourceClass, ResourceMetadata $resourceMetadata) : array
    {
        $messages = [];

        foreach ($resourceMetadata->getItemOperations() ?? [] as $itemOperationName => $itemOperation) {
            /** @var class-string|null $messageClass */
            $messageClass = $this->messageMapping[$resourceClass][OperationType::ITEM][$itemOperationName] ?? null;

            if (! $messageClass) {
                continue;
            }

            $messages[$messageClass] = $itemOperation;
        }

        foreach ($resourceMetadata->getCollectionOperations() ?? [] as $collectionOperationName => $collectionOperation) {
            /** @var class-string|null $messageClass */
            $messageClass = $this->messageMapping[$resourceClass][OperationType::COLLECTION][$collectionOperationName] ?? null;

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
    private function paths(array $messages) : array
    {
        $paths = [];

        $schemas = $this->schemas($messages);

        foreach ($messages as $messageClass => $operation) {
            if (! isset($this->operationMapping[$messageClass])) {
                throw ApiPlatformMappingException::noOperationFound($messageClass);
            }

            $operation = array_merge(
                $this->operationMapping[$messageClass],
                $operation
            );
            $operation['resourceShortName'] = (new ReflectionClass($operation['resource']))->getShortName();
            $schema = $schemas[$messageClass];
            $method = strtolower($operation['method']);
            $path = $this->path($operation);
            $operation = $this->operation($path, $schema, $operation, $messageClass);

            if (! isset($paths[$path->toString()])) {
                $paths[$path->toString()] = [];
            }

            $paths[$path->toString()][$method] = $operation;
        }

        return $paths;
    }

    /**
     * @param array<mixed> $operation
     */
    private function path(array $operation) : Uri
    {
        $path = $this->operationPathResolver->resolveOperationPath(
            $operation['resourceShortName'],
            $operation,
            $operation['operationType']
        );

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
    private function operation(Uri $path, array $schema, array $operation, string $messageClass) : array
    {
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
                $this->pathParameters($path, $schema)
            ),
            'responses' => $reflectionClass->implementsInterface(HasResponses::class)
                    ? $this->responses($messageClass)
                    : null,
        ]);

        if ($schema !== null) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => self::convertSchema($schema),
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
    public function pathParameters(Uri $path, array &$schema) : array
    {
        $pathParameterNames = array_map([StringUtil::class, 'camelize'], $path->toPathParameterNames());

        $pathParameters = array_map(
            static function (string $parameterName) use ($schema) {
                return [
                    'name' => StringUtil::decamilize($parameterName),
                    'schema' => self::convertSchema($schema['properties'][StringUtil::camelize($parameterName)]),
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
     * @return array<array<string, mixed>>
     */
    public function responses(string $messageClass) : array
    {
        return array_map(
            static function (TypeSchema $response) {
                return [
                    'description' => '',
                    'content' => [
                        'application/json' => [
                            'schema' => self::convertSchema($response->toArray()),
                        ],
                    ],
                ];
            },
            $messageClass::__responseSchemasPerStatusCode()
        );
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $parameters
     *
     * @return array<mixed>|null
     */
    private static function removeFromSchema(array $schema, array $parameters) : ?array
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
    private function schemas(array $messages) : array
    {
        $schemas = [];

        foreach ($messages as $messageClass => $operation) {
            $reflectionClass = new ReflectionClass($messageClass);

            if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
                continue;
            }

            $schemas[$messageClass] = $messageClass::__schema()->toArray();
        }

        return $schemas;
    }

    /**
     * @return array<mixed>
     */
    private function components() : array
    {
        return array_map(
            [self::class, 'convertSchema'],
            $this->eventEngine->compileCacheableConfig()['responseTypes']
        );
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private function convertSchema(array $jsonSchema) : array
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
                $jsonSchema['properties'][StringUtil::decamilize($propName)] = self::convertSchema($propSchema);
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

        if (isset($jsonSchema['enum'], $jsonSchema['type'])
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
    private function buildSchema(array $paths, array $tags, array $components) : array
    {
        return array_merge_recursive(
            $this->schemaFactory->create(),
            [
                'paths' => $paths,
                'tags' => $this->schemaFactory->createTags($tags),
                'components' => ['schemas' => $components],
            ]
        );
    }
}
