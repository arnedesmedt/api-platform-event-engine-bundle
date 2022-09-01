<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

use function class_implements;
use function in_array;
use function sprintf;
use function strtolower;
use function ucfirst;

final class OperationNameResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);

        /** @var ApiResource $resource */
        foreach ($resourceMetadataCollection as $i => $resource) {
            if (
                $resource->getClass() === null
                || $resource->getOperations() === null
                || in_array(JsonSchemaAwareRecord::class, class_implements($resource->getClass()) ?: [])
            ) {
                continue;
            }

            /**
             * @var string $operationName
             * @var HttpOperation $operation
             */
            foreach ($resource->getOperations() as $operationName => $operation) {
                $method = $operation->getMethod();
                $shortName = $operation->getShortName();
                $isCollection = $operation instanceof CollectionOperationInterface;

                if ($method === null || $shortName === null) {
                    continue;
                }

                $newOperationName = sprintf(
                    '%s%s%s',
                    strtolower($method),
                    ucfirst($shortName),
                    $isCollection ? 'Collection' : 'Item',
                );

                $resource
                    ->getOperations()
                    ->remove($operationName)
                    ->add($newOperationName, $operation->withName($newOperationName));
            }

            $resourceMetadataCollection[$i] = $resource->withOperations($resource->getOperations()->sort());
        }

        return $resourceMetadataCollection;
    }
}
