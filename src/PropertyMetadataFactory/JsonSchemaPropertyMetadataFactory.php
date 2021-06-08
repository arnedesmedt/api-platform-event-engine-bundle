<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;

use function in_array;
use function method_exists;
use function reset;

final class JsonSchemaPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $decorated;

    public function __construct(PropertyMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param class-string<JsonSchemaAwareRecord> $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        $reflectionClass = new ReflectionClass($resourceClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            return $propertyMetadata;
        }

        $schema = $resourceClass::__schema()->toArray();

        $examples = $schema['properties'][$property]['examples'] ?? [];
        $example = reset($examples);
        if ($example === false) {
            $example = null;
        }

        $propertyDefault = null;
        try {
            if (
                method_exists($resourceClass, 'propertyDefault')
                && method_exists($resourceClass, 'defaultProperties')
            ) {
                $propertyDefault = $resourceClass::propertyDefault($property, $resourceClass::defaultProperties());
            }
        } catch (RuntimeException $exception) {
        }

        return $propertyMetadata
            ->withRequired(in_array($property, $schema['required'] ?? []))
            ->withSchema($schema['properties'][$property] ?? null)
            ->withExample($example)
            ->withDefault($propertyDefault);
    }
}
