<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;
use function array_map;
use function array_search;
use function count;
use function is_array;
use function reset;
use function sprintf;

final class MessageSchemaFactory implements SchemaFactoryInterface
{
    private SchemaFactoryInterface $schemaFactory;
    private Finder $messageFinder;

    public function __construct(SchemaFactoryInterface $schemaFactory, Finder $messageFinder)
    {
        $this->schemaFactory = $schemaFactory;
        $this->messageFinder = $messageFinder;
    }

    /**
     * @param array<mixed>|null $serializerContext
     * @param Schema<string> $schema
     *
     * @return Schema<string>
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
    ) : Schema {
        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext(
                [
                    'resource_class' => $className,
                    'operation_type' => $operationType,
                    sprintf('%s_operation_name', $operationType) => $operationName,
                ]
            );
        } catch (RuntimeException $exception) {
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

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
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

        $schema = $schema ?? new Schema();
        $eventEngineSchema = $message::__schema()->toArray();

        // TODO write library that converts php json schema array to open api schema array
        $schema['type'] = $eventEngineSchema['type'];
        $schema['properties'] = array_map(
            static function (array $property) {
                if (is_array($property['type'])) {
                    $key = array_search('null', $property['type']);

                    if ($key !== false) {
                        $property['nullable'] = true;

                        unset($property['type'][$key]);
                    }

                    if (count($property['type']) === 1) {
                        $property['type'] = reset($property['type']);
                    }

                    // TODO use oneOf if multiple types exists
                }

                return $property;
            },
            $eventEngineSchema['properties'] ?? []
        );
        $schema['required'] = $eventEngineSchema['required'];

        return $schema;
    }
}
