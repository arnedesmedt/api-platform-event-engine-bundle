<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Util\ArrayUtil;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function array_key_exists;
use function array_merge;
use function method_exists;

final class MessageNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private AbstractNormalizer $decorated;
    private Finder $messageFinder;
    private EventEngine $eventEngine;
    private string $pageParameterName;
    private string $orderParameterName;

    public function __construct(
        AbstractNormalizer $decorated,
        Finder $messageFinder,
        EventEngine $eventEngine,
        string $pageParameterName = 'page',
        string $orderParameterName = 'order'
    ) {
        $this->decorated = $decorated;
        $this->messageFinder = $messageFinder;
        $this->eventEngine = $eventEngine;
        $this->pageParameterName = $pageParameterName;
        $this->orderParameterName = $orderParameterName;
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     *
     * @return mixed
     **/
    public function denormalize($data, string $type, ?string $format = null, array $context = [])
    {
        if ($context['message'] ?? null) {
            return $context['message'];
        }

        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext($context);
        } catch (FinderException $exception) {
            return $this->decorated->denormalize($data, $type, $format, $context);
        }

        return $this->eventEngine->messageFactory()->createMessageFromArray(
            $message,
            [
                'payload' => $this->messageData($message, $data, $context),
            ]
        );
    }

    /**
     * @param mixed $data
     */
    public function supportsDenormalization($data, string $type, ?string $format = null): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    /**
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return array<mixed>|ArrayObject<mixed, mixed>|string|int|float|bool|null
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        if ($object instanceof ImmutableRecord) {
            return ArrayUtil::toSnakeCasedKeys($object->toArray(), true);
        }

        return $this->decorated->normalize($object, $format, $context);
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if (! ($this->decorated instanceof SerializerAwareInterface)) {
            return;
        }

        $this->decorated->setSerializer($serializer);
    }

    /**
     * @param class-string $message
     * @param mixed $data
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function messageData(string $message, $data, array $context): array
    {
        if (
            method_exists($message, '__requestBodyArrayProperty')
            && $message::__requestBodyArrayProperty()
        ) {
            $data = [$message::__requestBodyArrayProperty() => $data];
        }

        if (array_key_exists($this->pageParameterName, $context['query_parameters'])) {
            unset($context['query_parameters'][$this->pageParameterName]);
        }

        if (array_key_exists($this->orderParameterName, $context['query_parameters'])) {
            unset($context['query_parameters'][$this->orderParameterName]);
        }

        $data = array_merge(
            $data,
            $context['path_parameters'] ?? [],
            $context['query_parameters'] ?? []
        );

        return ArrayUtil::toCamelCasedKeys($data, true);
    }
}
