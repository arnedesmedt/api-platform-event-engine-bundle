<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ADS\Util\StringUtil;
use ADS\ValueObjects\Implementation\TypeDetector;
use ADS\ValueObjects\ListValue;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_reduce;
use function array_unique;
use function class_exists;
use function in_array;
use function is_array;
use function sprintf;
use function stripslashes;
use function strpos;
use function substr;
use function trim;

final class PropertySchemaStateExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface
{
    // phpcs:ignore SlevomatCodingStandard.Classes.UnusedPrivateElements.WriteOnlyProperty
    private PropertyInfoExtractorInterface $propertyInfo;
    private ClassMetadataFactoryInterface $classMetadataFactory;

    private const TYPE_MAPPING = [
        JsonSchema::TYPE_ARRAY => Type::BUILTIN_TYPE_ARRAY,
        JsonSchema::TYPE_BOOL => Type::BUILTIN_TYPE_BOOL,
        JsonSchema::TYPE_FLOAT => Type::BUILTIN_TYPE_FLOAT,
        JsonSchema::TYPE_INT => Type::BUILTIN_TYPE_INT,
        JsonSchema::TYPE_NULL => Type::BUILTIN_TYPE_NULL,
        JsonSchema::TYPE_OBJECT => Type::BUILTIN_TYPE_OBJECT,
        JsonSchema::TYPE_STRING => Type::BUILTIN_TYPE_STRING,
    ];

    public function __construct(
        PropertyInfoExtractorInterface $propertyInfo,
        ClassMetadataFactoryInterface $classMetadataFactory
    ) {
        $this->propertyInfo = $propertyInfo;
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
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
            static fn (string $serializerGroup) => strpos($serializerGroup, '!') !== 0
        );

        if (empty($whiteListedSerializerGroups)) {
            $whiteListedSerializerGroups = null;
        }

        $blacklistedSerializerGroups = array_map(
            static fn (string $serializerGroup) => substr($serializerGroup, 1),
            array_filter(
                $serializerGroups,
                static fn (string $serializerGroup) => strpos($serializerGroup, '!') === 0
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
     * @return array<int, int|string>|null
     */
    public function getProperties(string $class, array $context = []): ?array
    {
        $properties = [];
        $context['serializer_groups'] ??= null;

        [$serializerGroups, $blackListedSerializerGroups] = self::splitSerializerGroups($context['serializer_groups']);

        // Only allow the properties that are listed in the json schema aware record, if it's such an object.
        $filteredPropertyNames = array_keys(
            $this->schemaFrom($class, false)['properties'] ?? []
        );

        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            $jsonSchemaAndPropertyNotInSchema = ! empty($filteredPropertyNames)
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

            $properties[] = StringUtil::decamelize($serializerAttributeMetadata->getName());
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

        return self::typeMapper($schema, $property);
    }

    /**
     * @param class-string $stateClass
     *
     * @return array<mixed>
     */
    private function schemaFrom(string $stateClass, bool $withReflectionProperties = true): ?array
    {
        $reflectionClass = new ReflectionClass($stateClass);

        if (
            ! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
            || $reflectionClass->isInterface()
        ) {
            return null;
        }

        $schema = $stateClass::__schema()->toArray();

        if (! $withReflectionProperties) {
            return $schema;
        }

        $schema['reflectionProperties'] = array_reduce(
            array_keys($schema['properties'] ?? []),
            static function ($reflectionProperties, $property) use ($reflectionClass) {
                $reflectionProperties[$property] = $reflectionClass->getProperty($property);

                return $reflectionProperties;
            },
            []
        );

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<Type>|null
     */
    private static function typeMapper(array $schema, string $property): ?array
    {
        $propertySchema = $schema['properties'][$property] ?? null;

        if ($propertySchema === null) {
            return null;
        }

        if (! isset($propertySchema['type'])) {
            return [new Type(Type::BUILTIN_TYPE_NULL)];
        }

        if (is_array($propertySchema['type'])) {
            return array_map(
                static fn (string $type) => self::type($schema, $property, $type),
                array_unique(
                    array_map(
                        static fn (string $type) => self::convertTypeIfComplex($type),
                        $propertySchema['type']
                    )
                )
            );
        }

        $type = self::convertTypeIfComplex($propertySchema['type']);

        return [self::type($schema, $property, $type)];
    }

    private static function convertTypeIfComplex(string $type): string
    {
        $type = stripslashes(trim($type));

        if (! class_exists($type)) {
            return $type;
        }

        return TypeDetector::getTypeFromClass($type, true, false)->toArray()['type'];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function type(array $schema, string $property, string $type): Type
    {
        $symfonyType = self::mapToSymfonyType($type);
        $nullable = ! in_array($property, $schema['required'] ?? [$property]);
//        $class = $schema['reflectionProperties'][$property]->
        $collection = $symfonyType === Type::BUILTIN_TYPE_ARRAY;
        $collectionKeyType = $collection ? new Type(Type::BUILTIN_TYPE_INT) : null;
        $collectionValueType = $collection ? self::collectionValueType($schema, $property) : null;

        $type = new Type($symfonyType, $nullable, null, $collection, $collectionKeyType, $collectionValueType);
//        $type = new Type($symfonyType, $nullable, $class, $collection, $collectionKeyType, $collectionValueType);

        return $type;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function collectionValueType(array $schema, string $property): Type
    {
        /** @var ReflectionProperty|null $reflectionProperty */
        $reflectionProperty = $schema['reflectionProperties'][$property] ?? null;

        if ($reflectionProperty === null) {
            return new Type(Type::BUILTIN_TYPE_OBJECT);
        }

        /** @var ReflectionNamedType|null $type */
        $type = $reflectionProperty->getType();

        if ($type === null) {
            return new Type(Type::BUILTIN_TYPE_OBJECT);
        }

        /** @var class-string $valueObjectClass */
        $valueObjectClass = $type->getName();

        try {
            $reflectionClass = new ReflectionClass($valueObjectClass);
        } catch (ReflectionException $exception) {
            return new Type(Type::BUILTIN_TYPE_OBJECT);
        }

        $typeClass = $reflectionClass->implementsInterface(ListValue::class)
            ? $valueObjectClass::itemType()
            : $valueObjectClass;

        return new Type(Type::BUILTIN_TYPE_OBJECT, false, $typeClass);
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
}
