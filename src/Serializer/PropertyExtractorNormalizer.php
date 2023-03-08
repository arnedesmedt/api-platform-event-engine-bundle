<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use function class_implements;
use function in_array;
use function is_string;
use function iterator_to_array;

final class PropertyExtractorNormalizer extends ObjectNormalizer
{
    /** @param array<mixed> $defaultContext */
    public function __construct(
        private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        ClassMetadataFactoryInterface|null $classMetadataFactory = null,
        NameConverterInterface|null $nameConverter = null,
        PropertyAccessorInterface|null $propertyAccessor = null,
        PropertyTypeExtractorInterface|null $propertyTypeExtractor = null,
        ClassDiscriminatorResolverInterface|null $classDiscriminatorResolver = null,
        callable|null $objectClassResolver = null,
        array $defaultContext = [],
    ) {
        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyAccessor,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext,
        );
    }

    /** @param array<string, mixed> $context */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        string|null $format = null,
        array $context = [],
    ): bool {
        return parent::supportsDenormalization($data, $type, $format)
            && in_array(JsonSchemaAwareRecord::class, class_implements($type) ?: []);
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, string|null $format = null, array $context = []): bool
    {
        return parent::supportsNormalization($data, $format) && $data instanceof JsonSchemaAwareRecord;
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<string>
     */
    protected function getAllowedAttributes(
        object|string $classOrObject,
        array $context,
        bool $attributesAsString = false,
    ): array {
        $iterator = $this->propertyNameCollectionFactory->create(
            is_string($classOrObject) ? $classOrObject : $classOrObject::class,
            $this->getFactoryOptions($context),
        )
            ->getIterator();

        /** @var array<string> $attributes */
        $attributes = iterator_to_array($iterator);

        return $attributes;
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
