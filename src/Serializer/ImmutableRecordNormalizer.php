<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Util\ArrayUtil;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function assert;

final class ImmutableRecordNormalizer implements NormalizerInterface
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
        assert($object instanceof ImmutableRecord);

        return ArrayUtil::toSnakeCasedKeys($object->toArray(), true);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ImmutableRecord && ! $data instanceof NoImmutableRecordSerializer;
    }
}
