<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\EventEngineBundle\Type\ComplexTypeExtractor;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: 'api_platform.metadata.property.metadata_factory', priority: 9)]
class ComplexPropertyMetadataFactory implements PropertyMetadataFactoryInterface
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

        $builtinTypes = $propertyMetadata->getBuiltinTypes();

        foreach ($builtinTypes ?? [] as $builtinType) {
            $className = $builtinType->getClassName();

            if ($className === null) {
                continue;
            }

            if (ComplexTypeExtractor::isClassComplexType($className)) {
                $schema = [
                    ...($propertyMetadata->getSchema() ?? []),
                    ...(['type' => ComplexTypeExtractor::complexType($className)]),
                ];

                return $propertyMetadata->withSchema($schema);
            }
        }

        return $propertyMetadata;
    }
}
