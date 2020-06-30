<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Bundle\EventEngineBundle\Util\ArrayUtil;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Messaging\MessageFactory;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function array_merge;

final class MessageNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private AbstractNormalizer $decorated;
    private Finder $messageFinder;
    private MessageFactory $messageFactory;

    public function __construct(
        AbstractNormalizer $decorated,
        Finder $messageFinder,
        MessageFactory $messageFactory
    ) {
        $this->decorated = $decorated;
        $this->messageFinder = $messageFinder;
        $this->messageFactory = $messageFactory;
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     *
     * @return mixed
     **/
    public function denormalize($data, string $type, ?string $format = null, array $context = [])
    {
        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext($context);
        } catch (RuntimeException $exception) {
            return $this->decorated->denormalize($data, $type, $format, $context);
        }

        return $this->messageFactory->createMessageFromArray(
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
            return $object->toArray();
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
        $data = array_merge(
            $data,
            $context['pathParameters'] ?? []
        );

        $data = ArrayUtil::toCamelCasedKeys($data, true);

//        if ($context['object_to_populate'] ?? false) {
//            $identifier = $this->eventEngineConfig->aggregateIdentifiers()[$message] ?? null;
//
//            if ($identifier === null) {
//                throw new RuntimeException(
//                    sprintf(
//                        'No identifier found for aggregate root class \'%s\'.',
//                        $message
//                    )
//                );
//            }
//
//            $data[$identifier] = $context['object_to_populate']->{$identifier}();
//        }

        return $data;
    }
}
