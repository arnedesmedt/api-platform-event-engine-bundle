<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\EventEngineBundle\Config;
use ADS\Bundle\EventEngineBundle\Repository\Repository;
use ADS\Bundle\EventEngineBundle\Util;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\Data\ImmutableRecord;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use function sprintf;

final class DocumentStoreItemDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private ContainerInterface $container;
    private DenormalizerInterface $denormalizer;
    private Config $eventEngineConfig;

    public function __construct(
        ContainerInterface $container,
        DenormalizerInterface $denormalizer,
        Config $eventEngineConfig
    ) {
        $this->container = $container;
        $this->denormalizer = $denormalizer;
        $this->eventEngineConfig = $eventEngineConfig;
    }

    /**
     * @param class-string $resourceClass
     * @param mixed $id
     * @param array<mixed> $context
     */
    public function getItem(
        string $resourceClass,
        $id,
        ?string $operationName = null,
        array $context = []
    ) : ?ImmutableRecord {
        if ($operationName === 'delete') {
            $aggregateIdentifiers = $this->eventEngineConfig->aggregateIdentifiers();
            $identifier = $aggregateIdentifiers[Util::fromStateToAggregateClass($resourceClass)] ?? null;

            $result = $this->denormalizer->denormalize([$identifier => $id], $resourceClass, null, $context);

            if ($result !== null && ! $result instanceof ImmutableRecord) {
                throw new RuntimeException(
                    sprintf(
                        'Could not denormalize request into delete command \'%s\'.',
                        $resourceClass
                    )
                );
            }

            return $result;
        }

        /** @var Repository $repository */
        $repository = $this->container->get(Util::fromStateToRepositoryId($resourceClass));

        return $repository->findDocumentState((string) $id);
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $context
     */
    public function supports(string $resourceClass, ?string $operationName = null, array $context = []) : bool
    {
        return $this->container->has(Util::fromStateToRepositoryId($resourceClass));
    }
}
