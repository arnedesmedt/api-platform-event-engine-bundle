<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use function array_keys;
use function array_map;
use function in_array;
use function is_array;
use function sprintf;

final class PropertyStateExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface
{
    // phpcs:ignore SlevomatCodingStandard.Classes.UnusedPrivateElements.WriteOnlyProperty
    private PropertyInfoExtractorInterface $propertyInfo;

    private const TYPE_MAPPING = [
        JsonSchema::TYPE_ARRAY => Type::BUILTIN_TYPE_ARRAY,
        JsonSchema::TYPE_BOOL => Type::BUILTIN_TYPE_BOOL,
        JsonSchema::TYPE_FLOAT => Type::BUILTIN_TYPE_FLOAT,
        JsonSchema::TYPE_INT => Type::BUILTIN_TYPE_INT,
        JsonSchema::TYPE_NULL => Type::BUILTIN_TYPE_NULL,
        JsonSchema::TYPE_OBJECT => Type::BUILTIN_TYPE_OBJECT,
        JsonSchema::TYPE_STRING => Type::BUILTIN_TYPE_STRING,
    ];

    public function __construct(PropertyInfoExtractorInterface $propertyInfo)
    {
        $this->propertyInfo = $propertyInfo;
    }

    /**
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<int, int|string>|null
     */
    public function getProperties(string $class, array $context = []) : ?array
    {
        $schema = $this->schemaFrom($class);

        if ($schema === null) {
            return null;
        }

        return array_keys($schema['properties']);
    }

    /**
     * @param class-string $class
     * @param array<mixed> $context
     *
     * @return array<Type>|null
     */
    public function getTypes(string $class, string $property, array $context = []) : ?array
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
    private function schemaFrom(string $stateClass) : ?array
    {
        $reflectionClass = new ReflectionClass($stateClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
            || $reflectionClass->isInterface()
        ) {
            return null;
        }

        return $stateClass::__schema()->toArray();
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<Type>|null
     */
    private static function typeMapper(array $schema, string $property) : ?array
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
                static function (string $type) use ($schema, $property) {
                    return self::type($schema, $property, $type);
                },
                $propertySchema['type']
            );
        }

        return [self::type($schema, $property, $propertySchema['type'])];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function type(array $schema, string $property, string $type) : Type
    {
        $symfonyType = self::mapToSymfonyType($type);
        $nullable = ! in_array($property, $schema['required'] ?? [$property]);
        $collection = $symfonyType === Type::BUILTIN_TYPE_ARRAY;
        $collectionKeyType = $collection ? new Type(Type::BUILTIN_TYPE_INT) : null;
        $collectionValueType = $collection ? new Type($schema['properties'][$property]['items']['type']) : null;

        $type = new Type($symfonyType, $nullable, null, $collection, $collectionKeyType, $collectionValueType);

        return $type;
    }

    private static function mapToSymfonyType(string $jsonSchemaType) : string
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
