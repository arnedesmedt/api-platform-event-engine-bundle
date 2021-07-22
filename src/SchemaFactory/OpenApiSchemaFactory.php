<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\DocumentationException;
use ADS\Util\StringUtil;
use ApiPlatform\Core\OpenApi\OpenApi;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function in_array;
use function is_array;
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
    public static function toOpenApiSchema(array $jsonSchema, string $version = OpenApi::VERSION): array
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
     * @param array<mixed> $jsonSchema
     *
     * @return array<mixed>
     */
    private static function addNullableProperty(array $jsonSchema, string $version = OpenApi::VERSION): array
    {
        if (isset($jsonSchema['type']) && is_array($jsonSchema['type'])) {
            $type = null;
            foreach ($jsonSchema['type'] as $possibleType) {
                if (mb_strtolower($possibleType) !== 'null') {
                    if ($type) {
                        throw DocumentationException::moreThanOneNullType($jsonSchema);
                    }

                    $type = $possibleType;
                } elseif ($version === OpenApi::VERSION) {
                    $jsonSchema['nullable'] = true;
                }
            }

            if ($version === OpenApi::VERSION) {
                $jsonSchema['type'] = $type;
            }
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
     * @param array<mixed> $schema
     *
     * @return string[]
     */
    public static function findTypeRefs(array $schema): array
    {
        $definitions = [];

        foreach ($schema as $name => &$value) {
            if ($name === '$ref') {
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
