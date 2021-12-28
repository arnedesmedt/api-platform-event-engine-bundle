<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\ValueObjects\ValueObject;
use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function assert;

final class ValueObjectNormalizer implements NormalizerInterface
{
    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        assert($object instanceof ValueObject);

        /** @var array<mixed>|string|int|float|bool $value */
        $value = $object->toValue();

        return $value;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof ValueObject;
    }
}
