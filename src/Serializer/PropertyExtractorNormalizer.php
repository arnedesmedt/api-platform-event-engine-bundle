<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use function get_class;
use function is_string;
use function iterator_to_array;

final class PropertyExtractorNormalizer extends ObjectNormalizer
{
    private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory;

    /**
     * @param array<mixed> $defaultContext
     */
    public function __construct(
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?NameConverterInterface $nameConverter = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable $objectClassResolver = null,
        array $defaultContext = []
    ) {
        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyAccessor,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext
        );

        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
    }

    /**
     * @param object|string $classOrObject
     * @param array<mixed> $context
     *
     * @return array<string>
     */
    protected function getAllowedAttributes($classOrObject, array $context, bool $attributesAsString = false): array
    {
        $iterator = $this->propertyNameCollectionFactory->create(
            is_string($classOrObject) ? $classOrObject : get_class($classOrObject),
            $this->getFactoryOptions($context)
        )
            ->getIterator();

        return iterator_to_array($iterator);
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function getFactoryOptions(array $context): array
    {
        $options = [];

        if (isset($context[self::GROUPS])) {
            $options['serializer_groups'] = (array) $context[self::GROUPS];
        }

        return $options;
    }
}
