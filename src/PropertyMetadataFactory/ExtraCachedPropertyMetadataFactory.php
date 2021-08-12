<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;

final class ExtraCachedPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $propertyMetadataFactory;

    public function __construct(PropertyMetadataFactoryInterface $propertyMetadataFactory)
    {
        $this->propertyMetadataFactory = $propertyMetadataFactory;
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        if (isset($_GET['complex'])) {
            $options['complex'] = $_GET['complex'];
        }

        return $this->propertyMetadataFactory->create($resourceClass, $property, $options);
    }
}
