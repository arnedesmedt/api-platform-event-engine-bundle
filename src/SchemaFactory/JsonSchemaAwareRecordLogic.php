<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\DocBlockUtil;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;
use ADS\ValueObjects\HasExamples;
use ADS\ValueObjects\Implementation\TypeDetector;
use ADS\ValueObjects\ValueObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\Exception\InvalidArgumentException;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function is_string;
use function sprintf;

use const ARRAY_FILTER_USE_KEY;

// phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps
trait JsonSchemaAwareRecordLogic
{
    use \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic {
        getTypeFromClass as notUsedGetTypeFromClass;
        generateSchemaFromPropTypeMap as parentGenerateSchemaFromPropTypeMap;
    }

    /**
     * @param array<mixed> $arrayPropTypeMap
     */
    private static function generateSchemaFromPropTypeMap(array $arrayPropTypeMap = []): Type
    {
        if (self::$__propTypeMap === null) {
            self::$__propTypeMap = self::buildPropTypeMap();
        }

        //To keep BC, we cache arrayPropTypeMap internally.
        //New recommended way to provide the map is that
        //one should override the static method self::arrayPropItemTypeMap()
        //Hence, we check if this method returns a non empty array and only in this case cache the map
        if (count($arrayPropTypeMap) && ! count(self::arrayPropItemTypeMap())) {
            self::$__arrayPropItemTypeMap = $arrayPropTypeMap;
        }

        $arrayPropTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

        if (self::$__schema === null) {
            $props = [];
            $docBlockFactory = DocBlockFactory::createInstance();
            $reflectionClass = new ReflectionClass(static::class);
            $examples = self::extractExamples($reflectionClass);

            foreach (self::$__propTypeMap as $prop => [$type, $isScalar, $isNullable]) {
                if ($isScalar) {
                    $props[$prop] = JsonSchema::schemaFromScalarPhpType($type, $isNullable);
                    continue;
                }

                if ($type === ImmutableRecord::PHP_TYPE_ARRAY) {
                    if (! array_key_exists($prop, $arrayPropTypeMap)) {
                        throw new InvalidArgumentException(
                            sprintf(
                                'Missing array item type in array property map. ' .
                                'Please provide an array item type for property %s.',
                                $prop
                            )
                        );
                    }

                    $arrayItemType = $arrayPropTypeMap[$prop];

                    if (self::isScalarType($arrayItemType)) {
                        $arrayItemSchema = JsonSchema::schemaFromScalarPhpType($arrayItemType, false);
                    } elseif ($arrayItemType === ImmutableRecord::PHP_TYPE_ARRAY) {
                        throw new InvalidArgumentException(
                            sprintf(
                                "Array item type of property %s must not be 'array', " .
                                'only a scalar type or an existing class can be used as array item type.',
                                $prop
                            )
                        );
                    } else {
                        $arrayItemSchema = self::getTypeFromClass($arrayItemType);
                    }

                    $props[$prop] = JsonSchema::array($arrayItemSchema);
                } else {
                    $props[$prop] = self::getTypeFromClass($type);
                }

                if (! $isNullable) {
                    continue;
                }

                $props[$prop] = JsonSchema::nullOr($props[$prop]);
            }

            foreach ($props as $propName => $prop) {
                if (! $reflectionClass->hasProperty($propName) || ! $prop instanceof AnnotatedType) {
                    continue;
                }

                $docBlockExamples = null;
                $reflectionProperty = $reflectionClass->getProperty($propName);
                if ($reflectionProperty->getDocComment() !== false) {
                    $docBlock = $docBlockFactory->create($reflectionProperty);
                    $docBlockExamples = $docBlock->getTagsByName('example');

                    $prop = $prop->describedAs(DocBlockUtil::summaryAndDescription($docBlock));
                }

                if ($examples[$propName] ?? false) {
                    $example = $examples[$propName];

                    if ($example instanceof ValueObject) {
                        $example = $example->toValue();
                    }

                    $prop = $prop->withExamples($example);
                } elseif (! empty($docBlockExamples ?? null)) {
                    $prop = $prop->withExamples(
                        ...array_map(
                            static function (Generic $generic) {
                                return Util::castFromString($generic->getDescription()->render());
                            },
                            $docBlockExamples
                        )
                    );
                }

                $props[$propName] = $prop;
            }

            $defaultProperties = array_merge(
                array_filter(
                    $reflectionClass->getDefaultProperties(),
                    static function ($propertyName) use ($props) {
                        return isset($props[$propertyName]);
                    },
                    ARRAY_FILTER_USE_KEY
                ),
                self::__optionalProperties()
            );

            $optionalProperties = [];
            foreach ($defaultProperties as $optionalPropertyNameOrKey => $optionalPropertyNameOrDefault) {
                $keyIsPropertyName = is_string($optionalPropertyNameOrKey);
                $optionalPropertyName = $keyIsPropertyName
                    ? $optionalPropertyNameOrKey
                    : $optionalPropertyNameOrDefault;

                $optionalProperties[$optionalPropertyName] = $props[$optionalPropertyName];

                unset($props[$optionalPropertyName]);

                // No default value is set. The property is just added as optional property
                // Or the scheme doesn't support defaults.
                if (
                    ! $keyIsPropertyName
                    || ! $optionalProperties[$optionalPropertyName] instanceof AnnotatedType
                ) {
                    continue;
                }

                $optionalProperties[$optionalPropertyName] = $optionalProperties[$optionalPropertyName]
                    ->withDefault(
                        $optionalPropertyNameOrDefault instanceof ValueObject ?
                            $optionalPropertyNameOrDefault->toValue() :
                            $optionalPropertyNameOrDefault
                    );
            }

            self::$__schema = JsonSchema::object($props, $optionalProperties);
        }

        return self::$__schema;
    }

    private static function getTypeFromClass(string $classOrType): Type
    {
        return TypeDetector::getTypeFromClass($classOrType, self::__allowNestedSchema());
    }

    /**
     * @return array<mixed>
     */
    private static function extractExamples(ReflectionClass $reflectionClass): array
    {
        switch (true) {
            case $reflectionClass->implementsInterface(ApiPlatformMessage::class):
                return static::__examples() ?? [];

            case $reflectionClass->implementsInterface(HasExamples::class)
                && $reflectionClass->implementsInterface(ImmutableRecord::class):
                return static::example()->toArray();
        }

        return [];
    }
}
