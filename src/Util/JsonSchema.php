<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Util;

use ApiPlatform\Core\JsonSchema\Schema;
use function array_map;
use function array_search;
use function count;
use function is_array;
use function reset;

final class JsonSchema
{
    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed>|null $schema
     *
     * @return Schema<mixed> $schema
     */
    public static function toApiPlatformSchema(array $jsonSchema, ?Schema $schema) : Schema
    {
        $schema ??= new Schema();

        $schema['type'] = $jsonSchema['type'];

        self::handleRequired($jsonSchema, $schema);
        self::handleProperties($jsonSchema, $schema);

        return $schema;
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     */
    private static function handleRequired(array $jsonSchema, Schema $schema) : void
    {
        if (! isset($jsonSchema['required'])) {
            return;
        }

        $schema['required'] = array_map(
            static function ($property) {
                return StringUtil::decamilize($property);
            },
            $jsonSchema['required']
        );
    }

    /**
     * @param array<mixed> $jsonSchema
     * @param Schema<mixed> $schema
     */
    private static function handleProperties(array $jsonSchema, Schema $schema) : void
    {
        if (! isset($jsonSchema['properties'])) {
            return;
        }

        $properties = array_map(
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

                if ($property['examples'] ?? false) {
                    $property['example'] = reset($property['examples']);

                    unset($property['examples']);
                }

                return $property;
            },
            $jsonSchema['properties']
        );

        $schema['properties'] = ArrayUtil::toSnakeCasedKeys($properties);
    }
}
