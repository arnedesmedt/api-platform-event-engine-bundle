<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\EventEngineBundle\PropertyInfo\PropertyReflection;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use EventEngine\JsonSchema\ProvidesValidationRules;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

use function array_merge;

#[AsDecorator(decorates: 'api_platform.metadata.property.metadata_factory', priority: 19)]
class ValidationRulesPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly PropertyMetadataFactoryInterface $decorated,
    ) {
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        $schema = $propertyMetadata->getSchema();

        if (isset($schema['format'])) {
            return $propertyMetadata;
        }

        $reflectionClasses = PropertyReflection::propertyTypeReflectionClassesFromClassAndProperty(
            $resourceClass,
            $property,
        );

        foreach ($reflectionClasses as $reflectionClass) {
            if (! $reflectionClass->implementsInterface(ProvidesValidationRules::class)) {
                continue;
            }

            /** @var class-string<ProvidesValidationRules> $class */
            $class = $reflectionClass->getName();

            $schema = array_merge($schema ?? [], $class::validationRules());
        }

        if ($schema === null) {
            return $propertyMetadata;
        }

        return $propertyMetadata->withSchema($schema);
    }
}
