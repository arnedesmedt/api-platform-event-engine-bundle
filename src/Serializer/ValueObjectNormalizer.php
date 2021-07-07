<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\ValueObjects\ValueObject;
use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ValueObjectNormalizer implements NormalizerInterface
{
    /**
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return array<mixed>|ArrayObject<mixed, mixed>|string|int|float|bool|null
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        return $object->toValue();
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $data instanceof ValueObject;
    }
}
