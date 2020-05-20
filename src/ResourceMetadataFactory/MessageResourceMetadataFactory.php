<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource\ChangeApiResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\DocBlockUtil;
use ADS\Bundle\EventEngineBundle\Util\EventEngineUtil;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

final class MessageResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    /** @var array<mixed> */
    private array $mapping;

    private DocBlockFactory $docBlockFactory;

    public function __construct(
        ResourceMetadataFactoryInterface $decorated,
        Config $config
    ) {
        $this->decorated = $decorated;
        $this->mapping = $config->apiPlatformMapping();
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass) : ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        /** @var array<string, array<mixed>>|null $collectionOperations */
        $collectionOperations = $resourceMetadata->getCollectionOperations();
        /** @var array<string, array<mixed>>|null $itemOperations */
        $itemOperations = $resourceMetadata->getItemOperations();

        if (! $collectionOperations && ! $itemOperations) {
            return $resourceMetadata;
        }

        $reflectionClass = new ReflectionClass($resourceClass);

        if ($reflectionClass->implementsInterface(ChangeApiResource::class)) {
            $resourceClass = EventEngineUtil::fromStateToAggregateClass($resourceClass);
        }

        if ($collectionOperations) {
            $collectionOperations = $this->extractMessageDoc($collectionOperations, $resourceClass, OperationType::COLLECTION);
            $resourceMetadata = $resourceMetadata->withCollectionOperations($collectionOperations);
        }

        if ($itemOperations) {
            $itemOperations = $this->extractMessageDoc($itemOperations, $resourceClass, OperationType::ITEM);
            $resourceMetadata = $resourceMetadata->withItemOperations($itemOperations);
        }

        return $resourceMetadata;
    }

    /**
     * @param array<string, array<mixed>> $operations
     *
     * @return array<string, array<mixed>>
     */
    private function extractMessageDoc(array $operations, string $entity, string $operationType) : array
    {
        $newOperations = [];

        foreach ($operations as $operationName => $operation) {
            if ($this->mapping[$entity][$operationType][$operationName] ?? false) {
                $reflectionClass = new ReflectionClass($this->mapping[$entity][$operationType][$operationName]);

                $docBlock = $this->docBlockFactory->create($reflectionClass);
                $operation['openapi_context']['summary'] = DocBlockUtil::summaryAndDescription($docBlock);
            }

            $newOperations[$operationName] = $operation;
        }

        return $newOperations;
    }
}
