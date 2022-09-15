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
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function in_array;
use function sprintf;
use function str_starts_with;
use function substr;

final class PropertySchemaStateExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface
{
    public function __construct(private ClassMetadataFactoryInterface $classMetadataFactory)
    {
    }

    /**
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<int, int|string>|null
     */
    public function getProperties(string $class, array $context = []): ?array
    {
        $schema = $this->schemaFrom($class);

        if ($schema === null) {
            return null;
        }

        $properties = [];
        /** @var array<string>|null $serializerGroups */
        $serializerGroups = $context['serializer_groups'] ??= null;
        [$serializerGroups, $blackListedSerializerGroups] = self::splitSerializerGroups($serializerGroups);

        /** @var array<string, mixed> $schemaProperties */
        $schemaProperties = $schema['properties'] ?? [];
        // Only allow the properties that are listed in the json schema aware record, if it's such an object.
        $filteredPropertyNames = array_keys(
            $schemaProperties
        );

        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            $jsonSchemaAndPropertyNotInSchema = $schema !== null
                && ! in_array($serializerAttributeMetadata->getName(), $filteredPropertyNames);
            $ignoredProperty = $serializerAttributeMetadata instanceof AttributeMetadataInterface
                && $serializerAttributeMetadata->isIgnored();
            $inBlackListedSerializerGroup = $blackListedSerializerGroups !== null
                && ! empty(array_intersect($serializerAttributeMetadata->getGroups(), $blackListedSerializerGroups));
            $notInSerializerGroups = $serializerGroups !== null
                && empty(array_intersect($serializerAttributeMetadata->getGroups(), $serializerGroups));

            if (
                $jsonSchemaAndPropertyNotInSchema
                || $ignoredProperty
                || $inBlackListedSerializerGroup
                || $notInSerializerGroups
            ) {
                continue;
            }

            $properties[] = $serializerAttributeMetadata->getName();
        }

        return $properties;
    }

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
     * Split serializer groups into the one that starts with a ! (blacklisted)
     * and the ones without a starting ! (whitelisted)
     *
     * Also remove the ! for blacklisted serializer groups
     *
     * @param array<string>|null $serializerGroups
     *
     * @return array<array<string>|null>
     */
    private static function splitSerializerGroups(?array $serializerGroups): array
    {
        if ($serializerGroups === null) {
            return [null, null];
        }

        $whiteListedSerializerGroups = array_filter(
            $serializerGroups,
            static fn (string $serializerGroup) => ! str_starts_with($serializerGroup, '!')
        );

        if (empty($whiteListedSerializerGroups)) {
            $whiteListedSerializerGroups = null;
        }

        $blacklistedSerializerGroups = array_map(
            static fn (string $serializerGroup) => substr($serializerGroup, 1),
            array_filter(
                $serializerGroups,
                static fn (string $serializerGroup) => str_starts_with($serializerGroup, '!')
            )
        );

        if (empty($blacklistedSerializerGroups)) {
            $blacklistedSerializerGroups = null;
        }

        return [
            $whiteListedSerializerGroups,
            $blacklistedSerializerGroups,
        ];
    }

    /**
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<Type>|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $reflectionClass = new ReflectionClass($class);

        try {
            $reflectionProperty = $reflectionClass->getProperty($property);
        } catch (ReflectionException) {
            return null;
        }

        $reflectionPropertyType = $reflectionProperty->getType();
        $namedReflectionTypes = $reflectionPropertyType instanceof ReflectionUnionType
            || $reflectionPropertyType instanceof ReflectionIntersectionType
            ? $reflectionPropertyType->getTypes()
            : [$reflectionPropertyType];

        $types = [];

        foreach ($namedReflectionTypes as $namedReflectionType) {
            if (! $namedReflectionType instanceof ReflectionNamedType || $namedReflectionType->isBuiltin()) {
                continue;
            }

            /** @var class-string $possibleClass */
            $possibleClass = $namedReflectionType->getName();
            /** @var ReflectionClass<ValueObject|ImmutableRecord> $propertyReflectionClass */
            $propertyReflectionClass = new ReflectionClass($possibleClass);
            if (! $propertyReflectionClass->implementsInterface(ValueObject::class)) {
                continue;
            }

            $types[] = self::type($propertyReflectionClass, $reflectionPropertyType);
        }

        return empty($types) ? null : $types;
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
