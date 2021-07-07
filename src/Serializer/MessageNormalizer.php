<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterFinder;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\SearchFilter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Util\ArrayUtil;
use EventEngine\EventEngine;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use function array_diff_key;
use function array_key_exists;
use function array_merge;
use function method_exists;

final class MessageNormalizer implements DenormalizerInterface
{
    private Finder $messageFinder;
    private EventEngine $eventEngine;
    private FilterFinder $filterFinder;
    private string $pageParameterName;
    private string $orderParameterName;
    private string $itemsPerPageParameterName;

    public function __construct(
        Finder $messageFinder,
        EventEngine $eventEngine,
        FilterFinder $filterFinder,
        string $pageParameterName = 'page',
        string $orderParameterName = 'order',
        string $itemsPerPageParameterName = 'items-per-page'
    ) {
        $this->messageFinder = $messageFinder;
        $this->eventEngine = $eventEngine;
        $this->filterFinder = $filterFinder;
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
                'payload' => $this->messageData($message, $data, $type, $context),
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
     * @param class-string $message
     * @param mixed $data
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function messageData(string $message, $data, string $type, array $context): array
    {
        if (
            method_exists($message, '__requestBodyArrayProperty')
            && $message::__requestBodyArrayProperty()
        ) {
            $data = [$message::__requestBodyArrayProperty() => $data];
        }

        $filter = ($this->filterFinder)($type, SearchFilter::class);

        if ($filter !== null) {
            $descriptions = $filter->getDescription($type);
            $context['query_parameters'] = array_diff_key($context['query_parameters'], $descriptions);
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
