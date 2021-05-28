<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterConverter;
use ADS\Util\ArrayUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;

use function array_map;
use function is_array;

final class DocumentStoreCollectionDataProvider implements
    ContextAwareCollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    private EventEngine $eventEngine;
    private FilterConverter $filterConverter;

    public function __construct(EventEngine $eventEngine, FilterConverter $filterConverter)
    {
        $this->eventEngine = $eventEngine;
        $this->filterConverter = $filterConverter;
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    public function getCollection(string $resourceClass, ?string $operationName = null, array $context = []): array
    {
        /** @var Message|null $message */
        $message = $context['message'] ?? null;

        if ($message === null) {
            throw FinderException::noMessageFound(
                $resourceClass,
                OperationType::COLLECTION,
                $operationName
            );
        }

        if (! empty($context['filters'] ?? [])) {
            $filter = $this->filterConverter->filter($context['filters']);
            $order = $this->filterConverter->order($context['filters']);

            $message = $filter ? $message->withAddedMetadata('filter', $filter) : $message;
            $message = $order ? $message->withAddedMetadata('order', $order) : $message;
        }

        return array_map(
            static function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                return ArrayUtil::toSnakeCasedKeys($item, true);
            },
            $this->eventEngine->dispatch($message)
        );
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []): bool
    {
        return (bool) ($context['message'] ?? null);
    }
}
