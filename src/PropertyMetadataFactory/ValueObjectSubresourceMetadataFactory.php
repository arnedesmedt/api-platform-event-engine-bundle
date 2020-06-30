<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\ValueObjects\IsIdentifier;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Property\SubresourceMetadata;
use ReflectionClass;

final class ValueObjectSubresourceMetadataFactory implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $decorated;

    public function __construct(PropertyMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if (! $propertyMetadata->hasSubresource()) {
            return $propertyMetadata;
        }

        /** @var SubresourceMetadata $subresource */
        $subresource = $propertyMetadata->getSubresource();
        /** @var class-string $class */
        $class = $subresource->getResourceClass();
        $reflectionClass = new ReflectionClass($class);

        if (! $reflectionClass->implementsInterface(IsIdentifier::class)) {
            return $propertyMetadata;
        }

        return $propertyMetadata->withSubresource(
            $subresource->withResourceClass($class::resource())
        );
    }
}
