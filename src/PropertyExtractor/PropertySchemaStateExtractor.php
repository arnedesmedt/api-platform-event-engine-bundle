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
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<Type>|null
     */
    public function getTypes(string $class, string $property, array $context = []): array|null
    {
        $propertyReflectionType = self::propertyReflectionType($class, $property);
        $propertyTypeReflectionClasses = self::propertyTypeReflectionClasses($propertyReflectionType);
        /** @var array<ReflectionClass<ValueObject>> $propertyValueObjectReflectionClasses */
        $propertyValueObjectReflectionClasses = array_filter(
            $propertyTypeReflectionClasses,
            static fn (ReflectionClass $propertyReflectionClass) => $propertyReflectionClass
                ->implementsInterface(ValueObject::class)
        );

        if (empty($propertyValueObjectReflectionClasses)) {
            return null;
        }

        return array_map(
            static fn (ReflectionClass $propertyValueObjectReflectionClass) => self::symfonyType(
                $propertyValueObjectReflectionClass,
                $propertyReflectionType,
            ),
            $propertyValueObjectReflectionClasses,
        );
    }

    /** @param class-string $class */
    public static function propertyReflectionType(string $class, string $property): ReflectionType|null
    {
        $reflectionClass = new ReflectionClass($class);

        try {
            $reflectionProperty = $reflectionClass->getProperty($property);
        } catch (ReflectionException) {
            return null;
        }

        return $reflectionProperty->getType();
    }

    /** @return array<ReflectionClass<object>> */
    public static function propertyTypeReflectionClasses(ReflectionType|null $reflectionType): array
    {
        if ($reflectionType === null) {
            return [];
        }

        $namedReflectionTypes = $reflectionType instanceof ReflectionUnionType
        || $reflectionType instanceof ReflectionIntersectionType
            ? $reflectionType->getTypes()
            : [$reflectionType];

        $propertyTypeReflectionClasses = [];

        foreach ($namedReflectionTypes as $namedReflectionType) {
            if (! $namedReflectionType instanceof ReflectionNamedType || $namedReflectionType->isBuiltin()) {
                continue;
            }

            /** @var class-string $possibleClass */
            $possibleClass = $namedReflectionType->getName();
            /** @var ReflectionClass<ValueObject|ImmutableRecord> $propertyTypeReflectionClass */
            $propertyTypeReflectionClass = new ReflectionClass($possibleClass);

            $propertyTypeReflectionClasses[] = $propertyTypeReflectionClass;
        }

        return $propertyTypeReflectionClasses;
    }

    /** @param ReflectionClass<ValueObject|ImmutableRecord> $reflectionClass */
    private static function symfonyType(
        ReflectionClass $reflectionClass,
        ReflectionType|null $reflectionPropertyType = null,
    ): Type {
        return new Type(
            self::builtInTypeFromReflectionClass($reflectionClass),
            $reflectionPropertyType?->allowsNull() ?? false,
            $reflectionClass->getName(),
            $reflectionClass->implementsInterface(ListValue::class),
            self::collectionKey(),
            self::collectionValue($reflectionClass),
        );
    }

    /** @return array<Type> */
    private static function collectionKey(): array
    {
        // todo don't hardcode this. But fetch it from the list. Metadata still needs to be added.
        return [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING)];
    }

    /** @param ReflectionClass<ValueObject|ImmutableRecord> $reflectionClass */
    private static function collectionValue(ReflectionClass $reflectionClass): Type|null
    {
        if (! $reflectionClass->implementsInterface(ListValue::class)) {
            return null;
        }

        /** @var class-string<ListValue<ValueObject|ImmutableRecord>> $class */
        $class = $reflectionClass->getName();
        /** @var class-string<ValueObject|ImmutableRecord> $itemClass */
        $itemClass = $class::itemType();

        return self::symfonyType(new ReflectionClass($itemClass));
    }

    /** @param ReflectionClass<ValueObject|ImmutableRecord> $reflectionClass */
    private static function builtInTypeFromReflectionClass(ReflectionClass $reflectionClass): string
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
                    $reflectionClass->getName(),
                ),
            )
        };
    }
}
