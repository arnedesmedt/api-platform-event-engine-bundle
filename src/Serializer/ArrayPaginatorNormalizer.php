<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ApiPlatform\State\Pagination\ArrayPaginator;
use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function assert;

final class ArrayPaginatorNormalizer implements NormalizerInterface
{
    public function __construct(private readonly NormalizerInterface $normalizer)
    {
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        assert($object instanceof ArrayPaginator);

        $normalized = [];
        foreach ($object as $item) {
            $normalized[] = $this->normalizer->normalize($item, $format, $context);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ArrayPaginator && $format === 'json';
    }
}
