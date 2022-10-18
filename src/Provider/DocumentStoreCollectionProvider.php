<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Resolver\InMemoryFilterResolver;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Messaging\MessageProducer;
use Traversable;

use function is_array;

/**
 * @template T of ImmutableRecord
 * @extends  Provider<T>
 */
final class DocumentStoreCollectionProvider extends Provider
{
    public function __construct(
        private readonly InMemoryFilterResolver $inMemoryFilterResolver,
        MessageProducer $eventEngine
    ) {
        parent::__construct($eventEngine);
    }

    /**
     * @param array<mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array<T>|object|null
     *
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|iterable|null
    {
        $message = $this->needMessage($context, $operation->getName());

        if (! empty($context['filters'] ?? [])) {
            $message = $message->withAddedMetadata('context', $context);
        }

        /** @var array<T>|object $result */
        $result = $this->eventEngine->produce($message);

        if (
            $result instanceof PartialPaginatorInterface
            || ! ($result instanceof Traversable || is_array($result))
        ) {
            return $result;
        }

        /** @var array<T>|object $filteredResult */
        $filteredResult = ($this->inMemoryFilterResolver
            ->setMetaData($message->metadata())
            ->setCollection($result))($message);

        return $filteredResult;
    }
}
