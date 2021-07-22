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
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        return new stdClass();
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $data instanceof CommandDispatchResult || $data instanceof CommandDispatchResultCollection;
    }
}
