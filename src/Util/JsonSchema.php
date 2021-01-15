<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Util;

use ADS\Bundle\EventEngineBundle\Util\ArrayUtil;
use ApiPlatform\Core\JsonSchema\Schema;

use function array_map;
use function array_search;
use function count;
use function is_array;
use function reset;
use function str_replace;

final class JsonSchema
{
    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed>|null $schema
     * @param Schema<mixed>|null $rootSchema
     *
     * @return Schema<mixed> $schema
     */
    public static function toApiPlatformSchema(
        array $jsonSchema,
        ?Schema $schema = null,
        ?Schema $rootSchema = null
    ): Schema {
        $version = $rootSchema ? $rootSchema->getVersion() : Schema::VERSION_OPENAPI;
        $schema ??= new Schema($version);
        $rootSchema ??= $schema;

        self::handleType($jsonSchema, $schema);
        self::handleRequired($jsonSchema, $schema);
        self::handleRef($jsonSchema, $schema, $rootSchema);
        self::handleExamples($jsonSchema, $schema);
        self::handleItems($jsonSchema, $schema, $rootSchema);
        self::handleProperties($jsonSchema, $schema, $rootSchema);

        return $schema;
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     */
    private static function handleType(array $jsonSchema, Schema $schema): void
    {
        if (! isset($jsonSchema['type'])) {
            return;
        }

        if (is_array($jsonSchema['type'])) {
            $key = array_search('null', $jsonSchema['type']);

            if ($key !== false) {
                $schema['nullable'] = true;
            }

            if (count($jsonSchema['type']) === 1) {
                $jsonSchema['type'] = reset($jsonSchema['type']);
            }

            // TODO use oneOf if multiple types exists
        }

        $schema['type'] = $jsonSchema['type'];
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     */
    private static function handleExamples(array $jsonSchema, Schema $schema): void
    {
        if (! isset($jsonSchema['examples'])) {
            return;
        }

        $schema['example'] = reset($jsonSchema['examples']);
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     */
    private static function handleRequired(array $jsonSchema, Schema $schema): void
    {
        if (! isset($jsonSchema['required'])) {
            return;
        }

        $schema['required'] = ArrayUtil::toSnakeCasedValues($jsonSchema['required']);
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     * @param Schema<mixed> $rootSchema
     */
    private static function handleRef(array $jsonSchema, Schema $schema, Schema $rootSchema): void
    {
        if (! isset($jsonSchema['$ref'])) {
            return;
        }

        $ref = $jsonSchema['$ref'];

        if ($schema->getVersion() === Schema::VERSION_OPENAPI) {
            $ref = str_replace('#/definitions/', '#/components/schemas/', $ref);
        }

        $schema['$ref'] = $ref;

        $definitionName = $schema->getRootDefinitionKey();
        $definitions = $rootSchema->getDefinitions();

        $definitions[$definitionName] = $definitionName;
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     * @param Schema<mixed> $rootSchema
     */
    private static function handleItems(array $jsonSchema, Schema $schema, Schema $rootSchema): void
    {
        if (! isset($jsonSchema['items'])) {
            return;
        }

        $schema['items'] = self::toApiPlatformSchema($jsonSchema['items'], null, $rootSchema);
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     * @param Schema<mixed> $rootSchema
     */
    private static function handleProperties(array $jsonSchema, Schema $schema, Schema $rootSchema): void
    {
        if (! isset($jsonSchema['properties'])) {
            return;
        }

        $properties = array_map(
            static function (array $property) use ($rootSchema) {
                return self::toApiPlatformSchema($property, null, $rootSchema);
            },
            $jsonSchema['properties']
        );

        $schema['properties'] = ArrayUtil::toSnakeCasedKeys($properties);
    }
}
