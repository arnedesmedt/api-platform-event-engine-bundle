<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\DocumentationException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use EventEngine\JsonSchema\JsonSchema;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_combine;
use function array_diff;
use function array_diff_key;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function json_encode;
use function mb_strtolower;
use function method_exists;
use function reset;
use function sprintf;
use function str_replace;
use function ucfirst;

final class DocumentationResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private Config $config;
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        ResourceMetadataFactoryInterface $decorated,
        Config $config
    ) {
        $this->decorated = $decorated;
        $this->config = $config;
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        $messageMapping = $this->config->messageMapping();

        if (! array_key_exists($resourceClass, $messageMapping)) {
            throw new RuntimeException(
                sprintf(
                    'No messages found for resource class \'%s\'. Resources with messages are: %s.',
                    $resourceClass,
                    json_encode(array_keys($messageMapping))
                )
            );
        }

        $resourceMessageMapping = $messageMapping[$resourceClass];

        foreach (OperationType::TYPES as $operationType) {
            $getMethod = sprintf('get%sOperations', ucfirst($operationType));
            $withMethod = sprintf('with%sOperations', ucfirst($operationType));

            $operations = $resourceMetadata->{$getMethod}() ?? [];
            $messages = MessageResourceMetadataFactory::filterApiPlatformMessages(
                $resourceMessageMapping[$operationType] ?? []
            );

            $operations = $this
                ->addOpenApiContext(
                    $operations,
                    $messages
                );

            $resourceMetadata = $resourceMetadata->{$withMethod}($operations);
        }

        return $resourceMetadata;
    }

    /**
     * @param array<string, array<mixed>> $operations
     * @param array<string, class-string<ApiPlatformMessage>> $messagesByOperationName
     *
     * @return array<string, mixed>
     */
    private function addOpenApiContext(
        array $operations,
        array $messagesByOperationName
    ): array {
        $operationKeys = array_keys($operations);

        /** @var array<string, mixed> $withOpenApiContext */
        $withOpenApiContext = array_combine(
            $operationKeys,
            array_map(
                function (string $operationName, $operation) use ($messagesByOperationName) {
                    /** @var class-string<ApiPlatformMessage>|false $messageClass */
                    $messageClass = $messagesByOperationName[$operationName] ?? false;

                    if ($messageClass) {
                        $reflectionClass = new ReflectionClass($messageClass);

                        $this
                            ->addDocumentation($operation, $reflectionClass)
                            ->addTags($operation, $messageClass)
                            ->addParameters(
                                $operation,
                                $messageClass
                            );
                    }

                    return $operation;
                },
                $operationKeys,
                $operations
            )
        );

        return $withOpenApiContext;
    }

    /**
     * @param array<mixed> $operation
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addDocumentation(array &$operation, ReflectionClass $reflectionClass): self
    {
        try {
            $docBlock = $this->docBlockFactory->create($reflectionClass);
            $operation['openapi_context']['summary'] = $docBlock->getSummary();
            $operation['openapi_context']['description'] = $docBlock->getDescription()->render();
        } catch (InvalidArgumentException $exception) {
        }

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addTags(array &$operation, string $messageClass): self
    {
        $operation['openapi_context']['tags'] = $messageClass::__tags();

        return $this;
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addParameters(
        array &$operation,
        string $messageClass
    ): self {
        if (! isset($operation['openapi_context']['parameters'])) {
            $operation['openapi_context']['parameters'] = [];
        }

        $schema = self::toOpenApiSchema($messageClass::__schema()->toArray());
        $uri = $messageClass::__path()
            ?? $operation['path']
            ?? null;

        if ($uri === null) {
            return $this;
        }

        $path = Uri::fromString($uri);

        $parameters = [
            'path' => $path->toPathParameterNames(),
            'query' => $path->toQueryParameterNames(),
        ];

        foreach ($parameters as $type => $parameterNames) {
            if ($schema === null) {
                break;
            }

            foreach ($parameterNames as $parameterName) {
                if (! isset($schema['properties'][$parameterName])) {
                    throw new RuntimeException(
                        sprintf(
                            'Parameter \'%s\' (uri: %s) not found in message \'%s\'.',
                            $parameterName,
                            $uri,
                            $messageClass,
                        )
                    );
                }

                $operation['openapi_context']['parameters'][] = [
                    'name' => $parameterName,
                    'in' => $type,
                    'schema' => $schema['properties'][$parameterName],
                    'required' => in_array($parameterName, $schema['required']),
                ];
            }

            $schema = self::removeParametersFromSchema($parameterNames, $schema);
        }

        if ($schema === null && $operation['method'] !== Request::METHOD_POST) {
            return $this;
        }

        if (
            $schema
            && method_exists($messageClass, '__requestBodyArrayProperty')
            && $messageClass::__requestBodyArrayProperty()
        ) {
            $schema = $schema['properties'][$messageClass::__requestBodyArrayProperty()];
        }

        $operation['openapi_context']['requestBody'] = [
            'required' => true,
            'content' => [
                $operation['method'] === Request::METHOD_PATCH
                    ? 'application/merge-patch+json'
                    : 'application/json' => [
                        'schema' => $schema ?? JsonSchema::object([])->toArray(),
                    ],
            ],
        ];

        return $this;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    public static function toOpenApiSchema(array $jsonSchema): array
    {
        $jsonSchema = self::addNullableProperty($jsonSchema);
        $jsonSchema = self::decamilizeProperties($jsonSchema);
        $jsonSchema = self::oneOf($jsonSchema);
        $jsonSchema = self::items($jsonSchema);
        $jsonSchema = self::useOpenApiRef($jsonSchema);
        $jsonSchema = self::noNullInStringEnum($jsonSchema);
        $jsonSchema = self::onlyOneExample($jsonSchema);

        return self::decamilizeRequired($jsonSchema);
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function addNullableProperty(array $jsonSchema): array
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

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function decamilizeProperties(array $jsonSchema): array
    {
        if (isset($jsonSchema['properties']) && is_array($jsonSchema['properties'])) {
            foreach ($jsonSchema['properties'] as $propName => $propSchema) {
                $decamilize = StringUtil::decamelize($propName);
                $jsonSchema['properties'][$decamilize] = self::toOpenApiSchema($propSchema);

                if ($decamilize === $propName) {
                    continue;
                }

                unset($jsonSchema['properties'][$propName]);
            }
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function oneOf(array $jsonSchema): array
    {
        if (isset($jsonSchema['oneOf']) && is_array($jsonSchema['oneOf'])) {
//            $key = array_search('null', $jsonSchema['oneOf']);
//            if ($key !== false) {
//                $jsonSchema['nullable'] = true;
//
//                unset($jsonSchema['oneOf'][$key]);
//            }

            foreach ($jsonSchema['oneOf'] as $oneOfName => $oneOfSchema) {
                $jsonSchema['oneOf'][$oneOfName] = self::toOpenApiSchema($oneOfSchema);
            }
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function items(array $jsonSchema): array
    {
        if (isset($jsonSchema['items']) && is_array($jsonSchema['items'])) {
            $jsonSchema['items'] = self::toOpenApiSchema($jsonSchema['items']);
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function useOpenApiRef(array $jsonSchema): array
    {
        if (isset($jsonSchema['$ref'])) {
            $jsonSchema['$ref'] = str_replace('definitions', 'components/schemas', $jsonSchema['$ref']);
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function noNullInStringEnum(array $jsonSchema): array
    {
        if (
            isset($jsonSchema['enum'], $jsonSchema['type'])
            && $jsonSchema['type'] === 'string'
            && in_array(null, $jsonSchema['enum'])
        ) {
            $jsonSchema['enum'] = array_filter($jsonSchema['enum']);
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function onlyOneExample(array $jsonSchema): array
    {
        if (isset($jsonSchema['examples'])) {
            $jsonSchema['example'] = reset($jsonSchema['examples']);

            unset($jsonSchema['examples']);
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function decamilizeRequired(array $jsonSchema): array
    {
        if (isset($jsonSchema['required'])) {
            $jsonSchema['required'] = array_map([StringUtil::class, 'decamelize'], $jsonSchema['required']);

            if (count($jsonSchema['required']) === 0) {
                unset($jsonSchema['required']);
            }
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $parameters
     * @param array<mixed> $schema
     *
     * @return array<mixed>|null
     */
    private static function removeParametersFromSchema(array $parameters, array $schema): ?array
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
}
