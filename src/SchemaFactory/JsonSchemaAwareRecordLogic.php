<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\Exception\InvalidArgumentException;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use function array_key_exists;
use function array_map;
use function count;
use function sprintf;

// phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps
trait JsonSchemaAwareRecordLogic
{
    use \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic {
        getTypeFromClass as notUsedGetTypeFromClass;
        generateSchemaFromPropTypeMap as parentGenerateSchemaFromPropTypeMap;
    }

    private static function getTypeFromClass(string $classOrType) : Type
    {
        return TypeDetector::getTypeFromClass($classOrType, self::__allowNestedSchema());
    }

    /**
     * @param array<mixed> $arrayPropTypeMap
     */
    private static function generateSchemaFromPropTypeMap(array $arrayPropTypeMap = []) : Type
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

            $reflectionClass = new ReflectionClass(static::class);
            foreach ($props as $propName => $prop) {
                if (! $reflectionClass->hasProperty($propName)) {
                    continue;
                }

                $reflectionProperty = $reflectionClass->getProperty($propName);
                $docBlock = $docBlockFactory->create($reflectionProperty);

                $examples = $docBlock->getTagsByName('example');

                if (! empty($examples)) {
                    $prop = $prop->withExamples(
                        ...array_map(
                            static function (Generic $generic) {
                                return $generic->getDescription()->getBodyTemplate();
                            },
                            $examples
                        )
                    );
                }

                $props[$propName] = $prop->describedAs(
                    $docBlock->getSummary() . '<br/>' . $docBlock->getDescription()->render()
                );
            }

            $optionalProps = [];
            foreach (self::__optionalProperties() as $optProp) {
                $optionalProps[$optProp] = $props[$optProp];
                unset($props[$optProp]);
            }

            self::$__schema = JsonSchema::object($props, $optionalProps);
        }

        return self::$__schema;
    }
}
