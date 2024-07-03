<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\ComplexTypeExtractor;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

final class ComplexMessageRefSchemaFactory implements SchemaFactoryInterface
{
    /**
     * @param class-string<JsonSchemaAwareRecord> $className
     * @param array<string, mixed>|null $serializerContext
     * @param Schema<mixed>|null $schema
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        Operation|null $operation = null,
        Schema|null $schema = null,
        array|null $serializerContext = null,
        bool $forceCollection = false,
    ): Schema {
        $schema ??= new Schema(Schema::VERSION_OPENAPI);

        if (! ComplexTypeExtractor::isClassComplexType($className)) {
            return $schema;
        }

        if ($forceCollection) {
            $schema['type'] = 'array';
            $schema['items'] = ['type' => ComplexTypeExtractor::complexType($className)];

            return $schema;
        }

        $schema['type'] = ComplexTypeExtractor::complexType($className);

        return $schema;
    }
}
