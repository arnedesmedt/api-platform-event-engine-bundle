<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\EventEngineBundle\PropertyInfo\PropertyExampleExtractor;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;

class ExamplePropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private readonly PropertyMetadataFactoryInterface $decorated,
        private readonly PropertyExampleExtractor $exampleExtractor,
    ) {
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if ($propertyMetadata->getExample()) {
            return $propertyMetadata;
        }

        $example = $this->exampleExtractor->fromClassAndProperty($resourceClass, $property);

        return $example === null ? $propertyMetadata : $propertyMetadata->withExample($example);
    }
}
