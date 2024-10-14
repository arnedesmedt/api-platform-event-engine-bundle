<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ApiPlatform\Problem\Serializer\ErrorNormalizerTrait;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use TeamBlue\Exception\HttpException\Metadata\Metadata;
use TeamBlue\Exception\HttpException\MetadataAwareException;
use Throwable;

use function assert;

#[AutoconfigureTag('serializer.normalizer')]
class ErrorNormalizer implements NormalizerInterface
{
    use ErrorNormalizerTrait;

    private readonly HubInterface $sentryHub;

    public function __construct(
        #[Autowire('@?request_stack')]
        private readonly RequestStack|null $requestStack,
    ) {
        $this->sentryHub = SentrySdk::getCurrentHub();
    }

    final public const FORMAT = 'jsonproblem';

    /**
     * {@inheritDoc}
     *
     * @param Throwable $object
     * @param string    $format
     * @param mixed[]   $context
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<int|string, mixed>|null
     */
    public function normalize(
        mixed $object,
        string|null $format = null,
        array $context = [],
    ): array|string|int|float|bool|ArrayObject|null {
        assert($object instanceof ImmutableRecord);

        if ($object instanceof MetadataAwareException) {
            $object->withMetadata(
                Metadata::fromArray(
                    [
                        'request_id' => $this->requestStack?->getCurrentRequest()?->headers->get('X-Request-ID'),
                        'sentry_trace' => $this->sentryHub->pushScope()->getPropagationContext()->getTraceContext(),
                    ],
                ),
            );
        }

        return $object->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed[] $context
     */
    public function supportsNormalization(mixed $data, string|null $format = null, array $context = []): bool
    {
        return $format === self::FORMAT
            && $data instanceof ImmutableRecord
            && $data instanceof Throwable;
    }

    /** @return array<string, bool> */
    public function getSupportedTypes(string|null $format): array
    {
        return $format === self::FORMAT
            ? [
                ImmutableRecord::class => true,
                Throwable::class => true,
            ]
            : [];
    }
}
