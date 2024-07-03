<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\EventEngineBundle\PropertyInfo\PropertyDefaultExtractor;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: 'api_platform.metadata.property.metadata_factory', priority: 29)]
class DefaultPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly PropertyMetadataFactoryInterface $decorated,
        private readonly PropertyDefaultExtractor $defaultExtractor,
    ) {
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if ($propertyMetadata->getDefault()) {
            return $propertyMetadata;
        }

        $default = $this->defaultExtractor->fromClassAndProperty($resourceClass, $property);

        return $default === null ? $propertyMetadata : $propertyMetadata->withDefault($default);
    }
}
