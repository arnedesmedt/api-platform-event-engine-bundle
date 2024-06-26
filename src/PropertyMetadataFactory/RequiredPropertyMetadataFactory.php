<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\EventEngineBundle\PropertyInfo\PropertyRequiredExtractor;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;

class RequiredPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private readonly PropertyMetadataFactoryInterface $decorated,
        private readonly PropertyRequiredExtractor $requiredExtractor,
    ) {
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if ($propertyMetadata->isRequired() !== null) {
            return $propertyMetadata;
        }

        $isRequired = $this->requiredExtractor->fromClassAndProperty($resourceClass, $property);

        return $isRequired === null ? $propertyMetadata : $propertyMetadata->withRequired($isRequired);
    }
}
