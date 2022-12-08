<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ADS\ValueObjects\BoolValue;
use ADS\ValueObjects\FloatValue;
use ADS\ValueObjects\IntValue;
use ADS\ValueObjects\ListValue;
use ADS\ValueObjects\StringValue;
use ADS\ValueObjects\ValueObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

use function array_filter;
use function array_map;
use function sprintf;

final class PropertySchemaStateExtractor implements PropertyTypeExtractorInterface
{
    /**
     * @param class-string $stateClass
     *
     * @return array<mixed>
     */
    private function schemaFrom(string $stateClass): ?array
    {
        $reflectionClass = new ReflectionClass($stateClass);

        if (
            ! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
            || $reflectionClass->isInterface()
        ) {
            return null;
        }

        return $stateClass::__schema()->toArray();
    }

    /**
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<Type>|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $reflectionPropertyType = self::reflectionPropertyType($class, $property);
        $objectTypes = self::objectTypes($reflectionPropertyType);
        /** @var array<ReflectionClass<ValueObject>> $valueObjectTypes */
        $valueObjectTypes = array_filter(
            $objectTypes,
            static fn (ReflectionClass $propertyReflectionClass) => $propertyReflectionClass
                ->implementsInterface(ValueObject::class)
        );

        if (empty($valueObjectTypes)) {
            return null;
        }

        return array_map(
            static fn (ReflectionClass $type) => self::type($type, $reflectionPropertyType),
            $valueObjectTypes
        );
    }

    /**
     * @param class-string $class
     */
    public static function reflectionPropertyType(string $class, string $property): ?ReflectionType
    {
        $reflectionClass = new ReflectionClass($class);

        try {
            $reflectionProperty = $reflectionClass->getProperty($property);
        } catch (ReflectionException) {
            return null;
        }

        return $reflectionProperty->getType();
    }

    /**
     * @return array<ReflectionClass<object>>
     */
    public static function objectTypes(?ReflectionType $reflectionType): array
    {
        if ($reflectionType === null) {
            return [];
        }

        $namedReflectionTypes = $reflectionType instanceof ReflectionUnionType
        || $reflectionType instanceof ReflectionIntersectionType
            ? $reflectionType->getTypes()
            : [$reflectionType];

        $types = [];

        foreach ($namedReflectionTypes as $namedReflectionType) {
            if (! $namedReflectionType instanceof ReflectionNamedType || $namedReflectionType->isBuiltin()) {
                continue;
            }

            /** @var class-string $possibleClass */
            $possibleClass = $namedReflectionType->getName();
            /** @var ReflectionClass<ValueObject|ImmutableRecord> $propertyReflectionClass */
            $propertyReflectionClass = new ReflectionClass($possibleClass);

            $types[] = $propertyReflectionClass;
        }

        return $types;
    }

    /**
     * @param ReflectionClass<ValueObject|ImmutableRecord> $reflectionClass
     */
    private static function type(ReflectionClass $reflectionClass, ?ReflectionType $reflectionPropertyType = null): Type
    {
        $symfonyType = self::mapReflectionClassToSymfonyType($reflectionClass);

        return new Type(
            $symfonyType,
            $reflectionPropertyType?->allowsNull() ?? false,
            $reflectionClass->getName(),
            $reflectionClass->implementsInterface(ListValue::class),
            self::collectionKey(),
            self::collectionValue($reflectionClass)
        );
    }

    /**
     * @return array<Type>
     */
    private static function collectionKey(): array
    {
        return [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING)];
    }

    /**
     * @param ReflectionClass<ValueObject|ImmutableRecord> $reflectionClass
     */
    private static function collectionValue(ReflectionClass $reflectionClass): ?Type
    {
        if (! $reflectionClass->implementsInterface(ListValue::class)) {
            return null;
        }

        /** @var class-string<ListValue<ValueObject|ImmutableRecord>> $class */
        $class = $reflectionClass->getName();
        /** @var class-string<ValueObject|ImmutableRecord> $itemClass */
        $itemClass = $class::itemType();

        return self::type(new ReflectionClass($itemClass));
    }

    /**
     * @param ReflectionClass<ValueObject|ImmutableRecord> $reflectionClass
     */
    private static function mapReflectionClassToSymfonyType(ReflectionClass $reflectionClass): string
    {
        return match (true) {
            $reflectionClass->implementsInterface(StringValue::class) => Type::BUILTIN_TYPE_STRING,
            $reflectionClass->implementsInterface(ListValue::class) => Type::BUILTIN_TYPE_ARRAY,
            $reflectionClass->implementsInterface(BoolValue::class) => Type::BUILTIN_TYPE_BOOL,
            $reflectionClass->implementsInterface(FloatValue::class) => Type::BUILTIN_TYPE_FLOAT,
            $reflectionClass->implementsInterface(IntValue::class) => Type::BUILTIN_TYPE_INT,
            $reflectionClass->implementsInterface(ImmutableRecord::class) => Type::BUILTIN_TYPE_OBJECT,
            default => throw new RuntimeException(
                sprintf(
                    'No symfony type mapping found for class \'%s\'.',
                    $reflectionClass->getName()
                )
            )
        };
    }
}
