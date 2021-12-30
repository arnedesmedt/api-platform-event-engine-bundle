<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\CommandDispatchResultCollection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CommandDispatchResultNormalizer implements NormalizerInterface
{
    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        return [];
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof CommandDispatchResult || $data instanceof CommandDispatchResultCollection;
    }
}
