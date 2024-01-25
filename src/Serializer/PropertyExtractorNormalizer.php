<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function array_keys;
use function assert;
use function class_implements;
use function in_array;
use function is_string;
use function iterator_to_array;

final class PropertyExtractorNormalizer extends AbstractObjectNormalizer
{
    /** @param array<mixed> $defaultContext */
    public function __construct(
        private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        ClassMetadataFactoryInterface|null $classMetadataFactory = null,
        NameConverterInterface|null $nameConverter = null,
        PropertyTypeExtractorInterface|null $propertyTypeExtractor = null,
        ClassDiscriminatorResolverInterface|null $classDiscriminatorResolver = null,
        callable|null $objectClassResolver = null,
        array $defaultContext = [],
    ) {
        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext,
        );
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(
        mixed $data,
        string|null $format = null,
        array $context = [],
    ): bool {
        return parent::supportsNormalization($data, $format)
            && $data instanceof JsonSchemaAwareRecord;
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

    /**
     * @param array<string, mixed> $context
     *
     * @inheritDoc
     */
    protected function extractAttributes(object $object, string|null $format = null, array $context = []): array
    {
        assert($object instanceof JsonSchemaAwareRecord);

        $array = $object->toArray();

        return array_keys($array);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @inheritDoc
     */
    protected function getAttributeValue(
        object $object,
        string $attribute,
        string|null $format = null,
        array $context = [],
    ): mixed {
        assert($object instanceof JsonSchemaAwareRecord);

        $array = $object->toArray();

        return $array[$attribute];
    }

    /** @param array<string, mixed> $context */
    protected function setAttributeValue(
        object $object,
        string $attribute,
        mixed $value,
        string|null $format = null,
        array $context = [],
    ): void {
        assert($object instanceof JsonSchemaAwareRecord);

        $object->with([$attribute => $value]);
    }

    /** @inheritDoc */
    public function getSupportedTypes(string|null $format): array
    {
        return [JsonSchemaAwareRecord::class => true];
    }
}
