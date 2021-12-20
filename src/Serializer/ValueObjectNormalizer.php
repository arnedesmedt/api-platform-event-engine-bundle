<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\ValueObjects\ValueObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function assert;

final class ValueObjectNormalizer implements NormalizerInterface
{
    /**
     * @param array<mixed> $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        assert($object instanceof ValueObject);

        return $object->toValue();
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof ValueObject;
    }
}
