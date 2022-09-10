<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ArrayObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class EnvelopeNormalizer implements NormalizerInterface
{
    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|ArrayObject<int|string, mixed>|string|int|float|bool|null
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): array|ArrayObject|string|int|float|bool|null {
        return null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Envelope;
    }
}
