<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Operation;

use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use RuntimeException;

class OperationResolver
{
    /**
     * @return array<string, mixed>
     */
    public static function fromResourceMetadataOperationTypeAndName(
        ResourceMetadata $resourceMetadata,
        string $operationType,
        string $operationName
    ): array {
        $operations = match ($operationType) {
            OperationType::COLLECTION => $resourceMetadata->getCollectionOperations(),
            OperationType::ITEM => $resourceMetadata->getItemOperations(),
            OperationType::SUBRESOURCE => $resourceMetadata->getSubresourceOperations(),
            default => [],
        };

        if (! isset($operations[$operationName])) {
            throw new RuntimeException('No operation found for type \'%s\', with name \'%s\'.');
        }

        return $operations[$operationName];
    }
}
