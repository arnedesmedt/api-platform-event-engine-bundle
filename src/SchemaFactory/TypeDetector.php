<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ClassException;
use ADS\ValueObjects\BoolValue;
use ADS\ValueObjects\EnumValue;
use ADS\ValueObjects\FloatValue;
use ADS\ValueObjects\HasDefault;
use ADS\ValueObjects\HasExamples;
use ADS\ValueObjects\Implementation\Enum\StringEnumValue;
use ADS\ValueObjects\IntValue;
use ADS\ValueObjects\StringValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\JsonSchema\ProvidesValidationRules;
use EventEngine\JsonSchema\Type;
use ReflectionClass;

use function array_map;
use function call_user_func;
use function class_exists;
use function is_callable;
use function strrchr;
use function substr;

final class TypeDetector
{
    public static function getTypeFromClass(string $classOrType, bool $allowNestedSchema = true): Type
    {
        if (! class_exists($classOrType)) {
            return JsonSchema::typeRef($classOrType);
        }

        $refObj = new ReflectionClass($classOrType);

        if ($refObj->implementsInterface(JsonSchemaAwareRecord::class)) {
            $callback = [$classOrType, '__schema'];
            if ($allowNestedSchema && is_callable($callback)) {
                return call_user_func($callback);
            }

            $callback = [$classOrType, '__type'];
            if (is_callable($callback)) {
                return new Type\TypeRef(call_user_func($callback));
            }
        }

        $schemaType = self::determineScalarTypeOrListIfPossible($classOrType, $refObj);

        if ($schemaType) {
            return $schemaType;
        }

        return self::convertClassToType($classOrType);
    }

    /**
     * @param class-string $class
     * @param ReflectionClass<object> $refObj
     */
    private static function determineScalarTypeOrListIfPossible(string $class, ReflectionClass $refObj): ?Type
    {
        $schemaType = null;

        if ($refObj->implementsInterface(JsonSchemaAwareCollection::class)) {
            $callback = [$class, 'validationRules'];
            $validation = is_callable($callback)
                ? call_user_func($callback)
                : null;

            $callback = [$class, '__itemSchema'];
            if (is_callable($callback)) {
                $schemaType = JsonSchema::array(call_user_func($callback), $validation);
            }
        }

        if (! $schemaType) {
            $schemaType = $scalarSchemaType = self::determineScalarTypeIfPossible($class, $refObj);
        }

        if (! $schemaType) {
            return null;
        }

        if ($refObj->implementsInterface(HasDefault::class) && $schemaType instanceof AnnotatedType) {
            $schemaType = $schemaType->withDefault($class::defaultValue()->toValue());
        }

        if (
            ! $refObj->implementsInterface(HasExamples::class)
            || ! ($schemaType instanceof AnnotatedType)
        ) {
            return $schemaType;
        }

        return $schemaType->withExamples(
            ...array_map(
                static function (ValueObject $valueObject) {
                    return $valueObject->toValue();
                },
                $class::examples()
            )
        );
    }

    /**
     * @param class-string $class
     * @param ReflectionClass<object> $refObj
     */
    private static function determineScalarTypeIfPossible(string $class, ReflectionClass $refObj): ?Type
    {
        $validation = $refObj->implementsInterface(ProvidesValidationRules::class)
            ? $class::validationRules()
            : null;

        if ($refObj->implementsInterface(EnumValue::class)) {
            $possibleValues = $class::possibleValues();
            $type = $refObj->isSubclassOf(StringEnumValue::class)
                ? JsonSchema::TYPE_STRING
                : JsonSchema::TYPE_INT;

            return JsonSchema::enum($possibleValues, $type);
        }

        if ($refObj->implementsInterface(StringValue::class)) {
            return JsonSchema::string($validation);
        }

        if ($refObj->implementsInterface(IntValue::class)) {
            return JsonSchema::integer($validation);
        }

        if ($refObj->implementsInterface(FloatValue::class)) {
            return JsonSchema::float($validation);
        }

        if ($refObj->implementsInterface(BoolValue::class)) {
            return JsonSchema::boolean();
        }

        return null;
    }

    private static function convertClassToType(string $class): Type
    {
        $position = strrchr($class, '\\');

        if ($position === false) {
            throw ClassException::fullQualifiedClassNameWithoutBackslash($class);
        }

        $ref = substr($position, 1);

        return new Type\TypeRef($ref);
    }
}
