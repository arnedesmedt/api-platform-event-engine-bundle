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

use function array_combine;
use function array_keys;
use function array_map;
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
        $mapping = $this->config->messageMapping();

        /** @var array<string, class-string> $operationTypes */
        $operationTypes = $mapping[$entity][$operationType];
        $operationKeys = array_keys($operations);

        return array_combine(
            $operationKeys,
            array_map(
                function (string $operationName, $operation) use ($operationTypes) {
                    /** @var class-string<ApiPlatformMessage>|false $messageClass */
                    $messageClass = $operationTypes[$operationName] ?? false;

                    if ($messageClass) {
                        $reflectionClass = new ReflectionClass($messageClass);

                        $this->addDocumentation($operation, $reflectionClass);
                        $this->addHttpMethod($operation, $messageClass);
                        $this->addPath($operation, $messageClass);
                        $this->addController($operation, $messageClass);
                        $this->needRead($operation);
                    }

                    return $operation;
                },
                $operationKeys,
                $operations
            )
        );
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

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addHttpMethod(array &$operation, string $messageClass): void
    {
        if (isset($operation['method']) || $messageClass::__httpMethod() === null) {
            return;
        }

        $operation['method'] = $messageClass::__httpMethod();
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addPath(array &$operation, string $messageClass): void
    {
        if (isset($operation['path']) || $messageClass::__path() === null) {
            return;
        }

        $operation['path'] = $messageClass::__path();
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addController(array &$operation, string $messageClass): void
    {
        if (isset($operation['controller'])) {
            return;
        }

        $operation['controller'] = $messageClass::__controller();
    }
}
