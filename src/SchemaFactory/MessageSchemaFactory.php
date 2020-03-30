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

        $schema['type'] = $eventEngineSchema['type'];
        $schema['properties'] = array_map(
            static function ($property) {
                return $property;
            },
            $eventEngineSchema['properties'] ?? []
        );

        return $schema;
    }
}
