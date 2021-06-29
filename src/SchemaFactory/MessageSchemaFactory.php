<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Bundle\EventEngineBundle\Config;
use ADS\Bundle\EventEngineBundle\Exception\ResponseException;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use EventEngine\EventEngine;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use RuntimeException;

use function array_flip;
use function array_merge_recursive;
use function array_values;
use function is_callable;
use function sprintf;

final class MessageSchemaFactory implements SchemaFactoryInterface
{
    private SchemaFactoryInterface $schemaFactory;
    private Finder $messageFinder;
    private Config $config;
    private EventEngine $eventEngine;

    public function __construct(
        SchemaFactoryInterface $schemaFactory,
        Finder $messageFinder,
        Config $config,
        EventEngine $eventEngine
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->messageFinder = $messageFinder;
        $this->config = $config;
        $this->eventEngine = $eventEngine;
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

        $reflectionClass = new ReflectionClass($message);
        $schema ??= new Schema(Schema::VERSION_OPENAPI);

        if ($type === Schema::TYPE_OUTPUT && ! $reflectionClass->implementsInterface(HasResponses::class)) {
            return $schema;
        }

        $definitions = $schema->getDefinitions();
        $schema = new Schema(Schema::VERSION_OPENAPI);
        $schema->setDefinitions($definitions);

        $openApiSchema = $this->openApiSchema($type, $message, $serializerContext);

        $schemaArray = $schema->getArrayCopy();
        $openApiSchemaArray = $openApiSchema->toArray();

        $schemaArray = array_merge_recursive(
            $schemaArray,
            $openApiSchemaArray
        );

        if ($serializerContext['allowed_properties'] ?? false) {
            $schemaArray = self::filterParameters(
                $schemaArray,
                $serializerContext['allowed_properties'] ?? []
            );
        }

        if ($schemaArray === null) {
            return $schema;
        }

        $schema->exchangeArray(OpenApiSchemaFactory::toOpenApiSchema($schemaArray));
        $definitions = $schema->getDefinitions();

        $refs = OpenApiSchemaFactory::findTypeRefs($openApiSchemaArray);
        $responseTypes = $this->config->config()['responseTypes'];

        foreach ($refs as $ref) {
            if (! $this->eventEngine->isKnownType($ref)) {
                continue;
            }

            $definitions[$ref] = OpenApiSchemaFactory::toOpenApiSchema($responseTypes[$ref]);
        }

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
     * @param array<string, mixed>|null $serializerContext
     */
    private function openApiSchema(string $type, string $message, ?array $serializerContext): TypeSchema
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
     * @param array<mixed> $parameters
     *
     * @return array<mixed>|null
     */
    public static function removeParameters(array $schema, array $parameters): ?array
    {
        return self::changeParameters($schema, $parameters);
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $parameters
     *
     * @return array<mixed>|null
     */
    public static function filterParameters(array $schema, array $parameters): ?array
    {
        return self::changeParameters($schema, $parameters, 'array_intersect');
    }

    /**
     * @param array<mixed> $schema
     * @param array<mixed> $parameters
     *
     * @return array<mixed>|null
     */
    private static function changeParameters(
        array $schema,
        array $parameters,
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
        $filteredSchema['properties'] = $keyFilterMethod($schema['properties'], array_flip($parameters));

        if (empty($filteredSchema['properties'])) {
            return null;
        }

        $filteredSchema['required'] = array_values($filterMethod($schema['required'], $parameters));

        return $filteredSchema;
    }
}
