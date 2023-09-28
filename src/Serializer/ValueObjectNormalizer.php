<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\ValueObjects\ValueObject;
use ArrayObject;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function assert;
use function class_exists;
use function is_subclass_of;

final class ValueObjectNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<string, mixed>
     */
    public function normalize(mixed $object, string|null $format = null, array $context = []): mixed
    {
        assert($object instanceof ValueObject);

        /** @var array<mixed>|string|int|float|bool $value */
        $value = $object->toValue();

        return $value;
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, string|null $format = null, array $context = []): bool
    {
        return $data instanceof ValueObject;
    }

    /**
     * @param array<mixed>              $context
     * @param class-string<ValueObject> $type
     */
    public function denormalize(mixed $data, string $type, string|null $format = null, array $context = []): ValueObject
    {
        return $type::fromValue($data);
    }

    /** @param array<string, mixed> $context */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        string|null $format = null,
        array $context = [],
    ): bool {
        return class_exists($type) && is_subclass_of($type, ValueObject::class);
    }

    /** @return array<class-string, bool> */
    public function getSupportedTypes(string|null $format): array
    {
        return [ValueObject::class => true];
    }
}
