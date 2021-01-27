<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Util\ArrayUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\DataProvider\DenormalizedIdentifiersAwareItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use EventEngine\Messaging\Message;
use RuntimeException;

use function is_array;
use function print_r;
use function sprintf;

final class DocumentStoreItemDataProvider implements
    DenormalizedIdentifiersAwareItemDataProviderInterface,
    RestrictedDataProviderInterface
{
    private EventEngine $eventEngine;

    public function __construct(EventEngine $eventEngine)
    {
        $this->eventEngine = $eventEngine;
    }

    /**
     * @param class-string $resourceClass
     * @param mixed $id
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function getItem(
        string $resourceClass,
        $id,
        ?string $operationName = null,
        array $context = []
    ) {
        /** @var Message|null $message */
        $message = $context['message'] ?? null;

        if ($message === null) {
            throw FinderException::noMessageFound(
                $resourceClass,
                OperationType::ITEM,
                $operationName
            );
        }

        $item = $this->eventEngine->dispatch($message);

        if ($item instanceof ImmutableRecord) {
            $item = $item->toArray();
        }

        if (! is_array($item)) {
            throw new RuntimeException(
                sprintf('Result of item data provider is not an array. \'%s\' given.', print_r($item, true))
            );
        }

        return ArrayUtil::toSnakeCasedKeys($item, true);
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
