<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Util\ArrayUtil;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use ReflectionClass;
use stdClass;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function array_keys;
use function assert;
use function class_implements;
use function in_array;
use function is_array;
use function is_object;

final class ImmutableRecordNormalizer extends AbstractItemNormalizer
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $context */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        string|null $format = null,
        array $context = [],
    ): bool {
        $interfaces = class_implements($type);

        if ($interfaces === false) {
            return false;
        }

        return in_array(ImmutableRecord::class, $interfaces);
    }

    /** @param array<string, mixed> $context */
    public function denormalize(
        mixed $data,
        string $type,
        string|null $format = null,
        array $context = [],
    ): mixed {
        $initialDataIsEmpty = empty($data);
        $data = parent::denormalize($data, $type, $format, $context);

        if (($context['message_as_array'] ?? false) && is_array($data)) {
            return $data;
        }

        if (($context['message_as_array'] ?? false) && (! empty($this->data) || $initialDataIsEmpty)) {
            return $this->data;
        }

        $data = $this->data;
        $this->data = [];

        return $type::fromArray(ArrayUtil::toCamelCasedKeys($data, true));
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, string|null $format = null, array $context = []): bool
    {
        return $data instanceof ImmutableRecord;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|string|int|float|bool|ArrayObject<string, mixed>|null
     */
    public function normalize(
        mixed $object,
        string|null $format = null,
        array $context = [],
    ): array|string|int|float|bool|ArrayObject|null {
        if (! isset($context['resource_class']) && is_object($object)) {
            $context['resource_class'] = $object::class;
        }

        return parent::normalize($object, $format, $context);
    }

    /**
     * @param mixed                $object
     * @param mixed                $format
     * @param array<string, mixed> $context
     *
     * @return array<string>
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    protected function extractAttributes($object, $format = null, array $context = []): array
    {
        assert($object instanceof ImmutableRecord);

        return array_keys($object->toArray());
    }

    /** @param array<string, mixed> $context */
    protected function setAttributeValue(
        object $object,
        string $attribute,
        mixed $value,
        string|null $format = null,
        array $context = [],
    ): void {
        $this->data[$attribute] = $value;
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string, mixed>      $context
     * @param array<string, mixed>|bool $allowedAttributes
     * @param ReflectionClass<stdClass> $reflectionClass
     */
    protected function instantiateObject(
        array &$data,
        string $class,
        array &$context,
        ReflectionClass $reflectionClass,
        bool|array $allowedAttributes,
        string|null $format = null,
    ): object {
        return AbstractObjectNormalizer::instantiateObject(
            $data,
            $class,
            $context,
            $reflectionClass,
            $allowedAttributes,
            $format,
        );
    }
}
