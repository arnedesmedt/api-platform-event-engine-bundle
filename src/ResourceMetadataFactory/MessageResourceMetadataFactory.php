<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

use function in_array;

final class MessageResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private const COMMAND_METHODS = [
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
    ];

    private ResourceMetadataFactoryInterface $decorated;
    private Config $config;
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        ResourceMetadataFactoryInterface $decorated,
        Config $config
    ) {
        $this->decorated = $decorated;
        $this->config = $config;
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        /** @var array<string, array<mixed>>|null $collectionOperations */
        $collectionOperations = $resourceMetadata->getCollectionOperations();
        /** @var array<string, array<mixed>>|null $itemOperations */
        $itemOperations = $resourceMetadata->getItemOperations();

        if (! $collectionOperations && ! $itemOperations) {
            return $resourceMetadata;
        }

        if ($collectionOperations) {
            $collectionOperations = $this->handleMessageOperations(
                $collectionOperations,
                $resourceClass,
                OperationType::COLLECTION
            );
            $resourceMetadata = $resourceMetadata->withCollectionOperations($collectionOperations);
        }

        if ($itemOperations) {
            $itemOperations = $this->handleMessageOperations(
                $itemOperations,
                $resourceClass,
                OperationType::ITEM
            );
            $resourceMetadata = $resourceMetadata->withItemOperations($itemOperations);
        }

        return $resourceMetadata;
    }

    /**
     * @param array<string, array<mixed>> $operations
     *
     * @return array<string, array<mixed>>
     */
    private function handleMessageOperations(array $operations, string $entity, string $operationType): array
    {
        $newOperations = [];

        $mapping = $this->config->messageMapping();

        foreach ($operations as $operationName => $operation) {
            /** @var class-string<ApiPlatformMessage>|false $messageClass */
            $messageClass = $mapping[$entity][$operationType][$operationName] ?? false;
            if ($messageClass) {
                $reflectionClass = new ReflectionClass($messageClass);

                $this->addDocumentation($operation, $reflectionClass);
                $this->needRead($operation);
            }

            $newOperations[$operationName] = $operation;
        }

        return $newOperations;
    }

    /**
     * @param array<mixed> $operation
     * @param ReflectionClass<ApiPlatformMessage> $reflectionClass
     */
    private function addDocumentation(array &$operation, ReflectionClass $reflectionClass): void
    {
        try {
            $docBlock = $this->docBlockFactory->create($reflectionClass);
            $operation['openapi_context']['summary'] = $docBlock->getSummary();
            $operation['openapi_context']['description'] = $docBlock->getDescription()->render();
        } catch (InvalidArgumentException $exception) {
        }
    }

    /**
     * @param array<mixed> $operation
     */
    private function needRead(array &$operation): void
    {
        if (! in_array($operation['method'], self::COMMAND_METHODS)) {
            return;
        }

        $operation['read'] = false;
    }
}
