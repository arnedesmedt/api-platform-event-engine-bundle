<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Util\ArrayUtil;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function array_key_exists;
use function array_merge;
use function method_exists;

final class MessageNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private Finder $messageFinder;
    private EventEngine $eventEngine;
    private string $pageParameterName;
    private string $orderParameterName;
    private string $itemsPerPageParameterName;

    public function __construct(
        Finder $messageFinder,
        EventEngine $eventEngine,
        string $pageParameterName = 'page',
        string $orderParameterName = 'order',
        string $itemsPerPageParameterName = 'items-per-page'
    ) {
        $this->messageFinder = $messageFinder;
        $this->eventEngine = $eventEngine;
        $this->pageParameterName = $pageParameterName;
        $this->orderParameterName = $orderParameterName;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
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

        /** @var class-string $message */
        $message = $this->messageFinder->byContext($context);

        return $this->eventEngine->messageFactory()->createMessageFromArray(
            $message,
            [
                'payload' => $this->messageData($message, $data, $context),
            ]
        );
    }

    /**
     * @param mixed $data
     * @param array<mixed> $context
     */
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->messageFinder->hasMessageByContext($context);
    }

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
        return $data instanceof ImmutableRecord;
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

        if (array_key_exists($this->itemsPerPageParameterName, $context['query_parameters'])) {
            unset($context['query_parameters'][$this->itemsPerPageParameterName]);
        }

        $data = array_merge(
            $data,
            $context['path_parameters'] ?? [],
            $context['query_parameters'] ?? []
        );

        return ArrayUtil::toCamelCasedKeys($data, true);
    }
}
