<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ADS\Bundle\EventEngineBundle\Util;
use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\DocumentStore\Filter\AnyFilter;
use Psr\Container\ContainerInterface;
use function array_map;

final class DocumentStoreCollectionDataProvider implements
    CollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<ImmutableRecord>
     */
    public function getCollection(string $resourceClass, ?string $operationName = null) : array
    {
        $repository = $this->container->get(Util::fromStateToRepositoryId($resourceClass));

        return array_map(
            static function (ImmutableRecord $state) {
                return $state->toArray();
            },
            $repository->findDocumentStates(new AnyFilter())
        );
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
