<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use TeamBlue\ValueObjects\Implementation\ListValue\IterableListValue;
use TeamBlue\ValueObjects\ListValue;
use TeamBlue\ValueObjects\ValueObject;

use function assert;

#[AutoconfigureTag('serializer.normalizer')]
final class ListValueNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ImmutableRecordNormalizer $immutableRecordNormalizer,
        private readonly ValueObjectNormalizer $valueObjectNormalizer,
    ) {
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<string, mixed>
     */
    public function normalize(
        mixed $object,
        string|null $format = null,
        array $context = [],
    ): array|string|int|float|bool|ArrayObject|null {
        assert($object instanceof ListValue);

        $object = ! $object instanceof IterableListValue ? $object->toItems() : $object;

        $normalized = [];
        foreach ($object as $item) {
            $normalized[] = $item instanceof ValueObject
                ? $this->valueObjectNormalizer->normalize($item, $format, $context)
                : $this->immutableRecordNormalizer->normalize($item, $format, $context);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, string|null $format = null, array $context = []): bool
    {
        return $data instanceof ListValue && $format === 'json';
    }

    /** @inheritDoc */
    public function getSupportedTypes(string|null $format): array
    {
        return [ListValue::class => true];
    }
}
