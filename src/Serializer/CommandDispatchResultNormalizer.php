<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\CommandDispatchResultCollection;
use stdClass;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CommandDispatchResultNormalizer implements NormalizerInterface
{
    /**
     * @param array<mixed> $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        return new stdClass();
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof CommandDispatchResult || $data instanceof CommandDispatchResultCollection;
    }
}
