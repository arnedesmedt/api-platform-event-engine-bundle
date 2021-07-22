<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Util\ArrayUtil;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ImmutableRecordNormalizer implements NormalizerInterface
{
    /**
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return array<mixed>|ArrayObject<mixed, mixed>|string|int|float|bool|null
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        return ArrayUtil::toSnakeCasedKeys($object->toArray(), true);
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $data instanceof ImmutableRecord && ! $data instanceof NoImmutableRecordSerializer;
    }
}
