<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\DocumentationException;
use ADS\Util\StringUtil;
use ApiPlatform\Core\JsonSchema\Schema;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function mb_strtolower;
use function preg_match;
use function reset;
use function sprintf;
use function str_replace;

final class OpenApiSchemaFactory
{
    /**
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    public static function toOpenApiSchema(array $jsonSchema, string $version = Schema::VERSION_OPENAPI): array
    {
        if ($_SERVER['APP_ENV'] === 'test') {
            // TODO: Testing framework uses new open api to validate
            $version = '3.1';
        }

        $jsonSchema = self::addNullableProperty($jsonSchema, $version);
        $jsonSchema = self::decamilizeProperties($jsonSchema);
        $jsonSchema = self::oneOf($jsonSchema);
        $jsonSchema = self::items($jsonSchema);
        $jsonSchema = self::useOpenApiRef($jsonSchema);
        $jsonSchema = self::noNullInStringEnum($jsonSchema);
        $jsonSchema = self::onlyOneExample($jsonSchema);

        return self::decamilizeRequired($jsonSchema);
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return Schema<string, mixed>
     */
    public static function toApiPlatformSchema(array $jsonSchema, string $version = Schema::VERSION_OPENAPI): Schema
    {
        $schema = new Schema($version);
        foreach (self::toOpenApiSchema($jsonSchema, $version) as $key => $value) {
            $schema[$key] = $value;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function addNullableProperty(array $jsonSchema, string $version = Schema::VERSION_OPENAPI): array
    {
        /** @var array<string>|null $types */
        $types = $jsonSchema['type'] ?? null;
        if (! is_array($types)) {
            return $jsonSchema;
        }

        $type = null;
        foreach ($types as $possibleType) {
            if (mb_strtolower($possibleType) !== 'null') {
                if ($type) {
                    throw DocumentationException::moreThanOneNullType($jsonSchema);
                }

                $type = $possibleType;
            } elseif ($version === Schema::VERSION_OPENAPI) {
                $jsonSchema['nullable'] = true;
            }
        }

        if ($version === Schema::VERSION_OPENAPI) {
            $jsonSchema['type'] = $type;
        }

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function decamilizeProperties(array $jsonSchema): array
    {
        /** @var array<string, array<mixed>>|null $properties */
        $properties = $jsonSchema['properties'] ?? null;
        if (! is_array($properties)) {
            return $jsonSchema;
        }

        foreach ($properties as $propName => $propSchema) {
            $decamilize = StringUtil::decamelize($propName);
            $properties[$decamilize] = self::toOpenApiSchema($propSchema);

            if ($decamilize === $propName) {
                continue;
            }

            unset($properties[$propName]);
        }

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function oneOf(array $jsonSchema): array
    {
        /** @var array<string, array<mixed>>|null $oneOf */
        $oneOf = $jsonSchema['oneOf'] ?? null;

        if (! is_array($oneOf)) {
            return $jsonSchema;
        }

        foreach ($oneOf as $oneOfName => $oneOfSchema) {
            $oneOf[$oneOfName] = self::toOpenApiSchema($oneOfSchema);
        }

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function items(array $jsonSchema): array
    {
        /** @var array<mixed>|null $items */
        $items = $jsonSchema['items'] ?? null;

        if (is_array($items)) {
            $jsonSchema['items'] = self::toOpenApiSchema($items);
        }

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function useOpenApiRef(array $jsonSchema): array
    {
        /** @var string|null $ref */
        $ref = $jsonSchema['$ref'] ?? null;

        if (! isset($ref)) {
            return $jsonSchema;
        }

        $jsonSchema['$ref'] = str_replace('definitions', 'components/schemas', $ref);

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function noNullInStringEnum(array $jsonSchema): array
    {
        $enum = $jsonSchema['enum'] ?? [];
        $type = $jsonSchema['type'] ?? null;

        if (
            $type === 'string'
            && is_array($enum)
            && in_array(null, $enum)
        ) {
            $jsonSchema['enum'] = array_filter($enum);
        }

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function onlyOneExample(array $jsonSchema): array
    {
        $examples = $jsonSchema['examples'] ?? null;
        if (! is_array($examples)) {
            return $jsonSchema;
        }

        $jsonSchema['example'] = reset($examples);
        unset($jsonSchema['examples']);

        return $jsonSchema;
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    private static function decamilizeRequired(array $jsonSchema): array
    {
        /** @var array<string>|null $required */
        $required = $jsonSchema['required'] ?? null;

        if (! is_array($required)) {
            return $jsonSchema;
        }

        $jsonSchema['required'] = array_map([StringUtil::class, 'decamelize'], $required);

        if (count($jsonSchema['required']) === 0) {
            unset($jsonSchema['required']);
        }

        return $jsonSchema;
    }

    /**
     * @param array<mixed> $schema
     *
     * @return string[]
     */
    public static function findTypeRefs(array $schema): array
    {
        $definitions = [];

        foreach ($schema as $name => &$value) {
            if ($name === '$ref') {
                assert(is_string($value));
                preg_match('~#/components/schemas/(.+)~', $value, $matches);

                $definitions[] = $matches[1];

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $definitions = array_merge(
                $definitions,
                self::findTypeRefs($value)
            );
        }

        return array_unique($definitions);
    }

    /**
     * @param array<string, mixed> $schema
     */
    public static function replaceRefs(array &$schema, string $refName, string $newRefName): void
    {
        foreach ($schema as $name => &$value) {
            if ($name === '$ref') {
                assert(is_string($value));
                preg_match('~#/components/schemas/(.+)~', $value, $matches);

                if ($matches[1] === $refName) {
                    $value = sprintf('#/components/schemas/%s', $newRefName);
                }

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            self::replaceRefs($value, $refName, $newRefName);
        }
    }
}
