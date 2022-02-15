<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ArrayObject;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\CommandDispatchResultCollection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CommandDispatchResultNormalizer implements NormalizerInterface
{
    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<int|string, mixed>|null
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|null {
        return null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof CommandDispatchResult || $data instanceof CommandDispatchResultCollection;
    }
}
