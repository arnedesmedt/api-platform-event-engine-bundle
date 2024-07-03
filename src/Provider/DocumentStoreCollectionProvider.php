<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\ApiPlatformEventEngineBundle\Resolver\InMemoryFilterResolver;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Messaging\MessageBag;
use EventEngine\Messaging\MessageProducer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Traversable;

use function is_array;

/**
 * @template T of ImmutableRecord
 * @extends  Provider<T>
 */
#[AutoconfigureTag('api_platform.state_provider')]
final class DocumentStoreCollectionProvider extends Provider
{
    public function __construct(
        private readonly InMemoryFilterResolver $inMemoryFilterResolver,
        #[Autowire('@ADS\Bundle\EventEngineBundle\Messenger\MessengerMessageProducer')]
        MessageProducer $eventEngine,
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
        $message = $this
            ->needMessage($context, $operation->getName())
            ->withAddedMetadata('context', $context);

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
            ->setCollection($result))($message->get(MessageBag::MESSAGE));

        return $filteredResult;
    }
}
