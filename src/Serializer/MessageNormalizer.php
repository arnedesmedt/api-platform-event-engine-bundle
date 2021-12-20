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
use function assert;
use function is_array;
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
     * @param array<string, mixed> $context
     **/
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
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
     * @param array<mixed> $context
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $this->messageFinder->hasMessageByContext($context);
    }

    /**
     * @param class-string $message
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     */
    private function messageData(string $message, mixed $data, string $type, array $context): array
    {
        if (
            method_exists($message, '__requestBodyArrayProperty')
            && $message::__requestBodyArrayProperty()
        ) {
            $data = [$message::__requestBodyArrayProperty() => $data];
        }

        assert(is_array($data));

        $filter = ($this->filterFinder)($type, SearchFilter::class);

        /** @var array<string, mixed> $queryParameters */
        $queryParameters = $context['query_parameters'] ?? [];
        /** @var array<string, mixed> $pathParameters */
        $pathParameters = $context['path_parameters'] ?? [];

        if ($filter !== null) {
            $descriptions = $filter->getDescription($type);
            $queryParameters = array_diff_key($queryParameters, $descriptions);
        }

        if (array_key_exists($this->pageParameterName, $queryParameters)) {
            unset($queryParameters[$this->pageParameterName]);
        }

        if (array_key_exists($this->orderParameterName, $queryParameters)) {
            unset($queryParameters[$this->orderParameterName]);
        }

        if (array_key_exists($this->itemsPerPageParameterName, $queryParameters)) {
            unset($queryParameters[$this->itemsPerPageParameterName]);
        }

        $data = array_merge(
            $data,
            $pathParameters,
            $queryParameters
        );

        return ArrayUtil::toCamelCasedKeys($data, true);
    }
}
