<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\EventEngineBundle\PropertyInfo\PropertyDeprecationExtractor;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use EventEngine\Data\ImmutableRecord;

use function class_implements;
use function in_array;

class DeprecationPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private readonly PropertyMetadataFactoryInterface $decorated,
        private readonly PropertyDeprecationExtractor $propertyDeprecationExtractor,
    ) {
    }

    /**
     * @param class-string $resourceClass
     * @param array<string, mixed> $options
     *
     * @inheritDoc
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if (
            ! in_array(ImmutableRecord::class, class_implements($resourceClass) ?: [])
            || ! $propertyMetadata->getDeprecationReason()
        ) {
            return $propertyMetadata;
        }

        $deprecationReason = $this->propertyDeprecationExtractor->fromClassAndProperty($resourceClass, $property);

        if ($deprecationReason) {
            $propertyMetadata = $propertyMetadata->withDeprecationReason($deprecationReason);
        }

        return $propertyMetadata;
    }
}
