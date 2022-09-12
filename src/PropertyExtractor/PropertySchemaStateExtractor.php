<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ADS\ValueObjects\Implementation\TypeDetector;
use ADS\ValueObjects\ListValue;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_unique;
use function class_exists;
use function in_array;
use function is_array;
use function is_string;
use function method_exists;
use function reset;
use function sprintf;
use function str_starts_with;
use function stripslashes;
use function substr;
use function trim;

final class PropertySchemaStateExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface
{
    private const TYPE_MAPPING = [
        JsonSchema::TYPE_ARRAY => Type::BUILTIN_TYPE_ARRAY,
        JsonSchema::TYPE_BOOL => Type::BUILTIN_TYPE_BOOL,
        JsonSchema::TYPE_FLOAT => Type::BUILTIN_TYPE_FLOAT,
        JsonSchema::TYPE_INT => Type::BUILTIN_TYPE_INT,
        JsonSchema::TYPE_NULL => Type::BUILTIN_TYPE_NULL,
        JsonSchema::TYPE_OBJECT => Type::BUILTIN_TYPE_OBJECT,
        JsonSchema::TYPE_STRING => Type::BUILTIN_TYPE_STRING,
    ];

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
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<Type>|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $schema = $this->schemaFrom($class);

        if ($schema === null) {
            return null;
        }

        /** @var array<string, array<string, mixed>> $schemaProperties */
        $schemaProperties = $schema['properties'] ?? [];
        $propertySchema = $schemaProperties[$property] ?? null;

        if ($propertySchema === null) {
            return null;
        }

        $reflectionClass = new ReflectionClass($class);
        $reflectionProperty = $reflectionClass->getProperty($property);
        /** @var ReflectionNamedType $reflectionNamedType */
        $reflectionNamedType = $reflectionProperty->getType();

        return self::types($propertySchema, $reflectionNamedType, $class, $property);
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
     * @param array<string, mixed>|null $propertySchema
     * @param ReflectionNamedType|class-string|null $reflectionNamedType
     * @param class-string|null $class
     *
     * @return array<Type>|null
     */
    private static function types(
        ?array $propertySchema,
        ReflectionNamedType|string|null $reflectionNamedType,
        ?string $class = null,
        ?string $property = null,
    ): ?array {
        if ($propertySchema === null) {
            return null;
        }

        if (! isset($propertySchema['type'])) {
            return [new Type(Type::BUILTIN_TYPE_NULL)];
        }

        if (! is_array($propertySchema['type'])) {
            $propertySchema['type'] = [$propertySchema['type']];
        }

        /** @var class-string|null $typeClass */
        $typeClass = null;
        $nullable = false;

        if (is_string($reflectionNamedType)) {
            $typeClass = $reflectionNamedType;
        } elseif ($reflectionNamedType instanceof ReflectionType) {
            /** @var class-string|null $typeClass */
            $typeClass = $reflectionNamedType->isBuiltin() ? null : $reflectionNamedType->getName();
            $nullable = $reflectionNamedType->allowsNull();
        }

        $itemClass = self::itemClass($typeClass, $reflectionNamedType, $class, $property);

        if (in_array('null', $propertySchema['type'])) {
            $nullable = true;
            $propertySchema['type'] = array_diff($propertySchema['type'], ['null']);
        }

        return array_map(
            static function (string $type) use ($propertySchema, $nullable, $typeClass, $itemClass) {
                $symfonyType = self::mapToSymfonyType($type);
                $collection = $symfonyType === Type::BUILTIN_TYPE_ARRAY;
                $collectionKeyType = null;
                $collectionValueType = null;

                if ($collection) {
                    $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
                    /** @var array<string, mixed> $propertySchemaItems */
                    $propertySchemaItems = $propertySchema['items'];
                    /** @var array<Type> $collectionValueTypes */
                    $collectionValueTypes = self::types($propertySchemaItems, $itemClass);
                    /** @var Type $collectionValueType */
                    $collectionValueType = reset($collectionValueTypes);
                }

                return new Type(
                    $symfonyType,
                    $nullable,
                    $typeClass,
                    $collection,
                    $collectionKeyType,
                    $collectionValueType
                );
            },
            array_unique(
                array_map(
                    static fn (string $type) => self::convertTypeIfComplex($type),
                    $propertySchema['type']
                )
            )
        );
    }

    private static function convertTypeIfComplex(string $type): string
    {
        $type = stripslashes(trim($type));

        if (! class_exists($type)) {
            return $type;
        }

        return TypeDetector::getTypeFromClass($type)->toArray()['type'];
    }

    private static function mapToSymfonyType(string $jsonSchemaType): string
    {
        /** @var string|null $symfonyType */
        $symfonyType = self::TYPE_MAPPING[$jsonSchemaType] ?? null;

        if ($symfonyType === null) {
            throw new RuntimeException(
                sprintf(
                    'No type mapping found for JSON Schema type \'%s\'.',
                    $jsonSchemaType
                )
            );
        }

        return $symfonyType;
    }

    /**
     * @param class-string|null $typeClass
     * @param class-string|null $class
     *
     * @return class-string|null
     */
    private static function itemClass(
        ?string $typeClass,
        ReflectionNamedType|string|null $reflectionNamedType,
        ?string $class,
        ?string $property
    ): ?string {
        if ($typeClass) {
            $classReflection = new ReflectionClass($typeClass);

            return $classReflection->implementsInterface(ListValue::class)
                ? $typeClass::itemType()
                : null;
        }

        if (
            $reflectionNamedType instanceof ReflectionNamedType
            && $reflectionNamedType->isBuiltin()
            && $reflectionNamedType->getName() === 'array'
            && $class !== null
            && $property !== null
            && method_exists($class, '__itemTypeMapping')
        ) {
            $result = $class::__itemTypeMapping()[$property] ?? null;

            if (class_exists($result)) {
                return $result;
            }
        }

        return null;
    }
}
