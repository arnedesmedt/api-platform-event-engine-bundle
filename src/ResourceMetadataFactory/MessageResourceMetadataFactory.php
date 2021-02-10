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
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;
use function sprintf;

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

        /** @var array<string, array<string, class-string<ApiPlatformMessage>>> $resourceMessageMapping */
        $resourceMessageMapping = $this->config->messageMapping()[$resourceClass];

        $collectionMessages = $this->filterApiPlatformMessages(
            $resourceMessageMapping[OperationType::COLLECTION] ?? []
        );

        $itemMessasges = $this->filterApiPlatformMessages(
            $resourceMessageMapping[OperationType::ITEM] ?? []
        );

        $collectionOperations = $this
            ->addOperations(
                $collectionOperations ?? [],
                $collectionMessages
            );

        $collectionOperations = $this
            ->decorateOperations(
                $collectionOperations ?? [],
                $collectionMessages
            );

        $resourceMetadata = $resourceMetadata->withCollectionOperations($collectionOperations);

        $itemOperations = $this
            ->addOperations(
                $itemOperations ?? [],
                $itemMessasges
            );

        $itemOperations = $this
            ->decorateOperations(
                $itemOperations ?? [],
                $itemMessasges
            );

        return $resourceMetadata->withItemOperations($itemOperations);
    }

    /**
     * @param array<string, array<mixed>> $existingOperations
     * @param array<string, class-string<ApiPlatformMessage>> $messagesByOperationName
     *
     * @return array<string, array<mixed>>
     */
    private function addOperations(array $existingOperations, array $messagesByOperationName): array
    {
        foreach ($messagesByOperationName as $operationName => $messageClass) {
            if (array_key_exists($operationName, $existingOperations)) {
                continue;
            }

            $existingOperations[$operationName] = [];
        }

        return $existingOperations;
    }

    /**
     * @param array<string, array<mixed>> $operations
     * @param array<string, class-string<ApiPlatformMessage>> $messagesByOperationName
     *
     * @return array<string, array<mixed>>
     */
    private function decorateOperations(array $operations, array $messagesByOperationName): array
    {
        $operationKeys = array_keys($operations);

        return array_combine(
            $operationKeys,
            array_map(
                function (string $operationName, $operation) use ($messagesByOperationName) {
                    /** @var class-string<ApiPlatformMessage>|false $messageClass */
                    $messageClass = $messagesByOperationName[$operationName] ?? false;

                    if ($messageClass) {
                        $reflectionClass = new ReflectionClass($messageClass);

                        $this->addDocumentation($operation, $reflectionClass);
                        $this->addHttpMethod($operation, $messageClass);
                        $this->addPath($operation, $messageClass);
                        $this->addController($operation, $messageClass);
                        $this->addRouteName($operation, $messageClass);
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
        if (isset($operation['method'])) {
            return;
        }

        if ($messageClass::__httpMethod() === null) {
            throw new RuntimeException(
                sprintf(
                    'No __httpMethod method found in class \'%s\'.',
                    $messageClass
                )
            );
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

        $operation['controller'] = $messageClass::__apiPlatformController();
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addRouteName(array &$operation, string $messageClass): void
    {
        if (isset($operation['route_name'])) {
            return;
        }

        $operation['route_name'] = $messageClass::__routeName();
    }

    /**
     * @param array<string, class-string> $messageClasses
     *
     * @return array<string, class-string<ApiPlatformMessage>>
     */
    private function filterApiPlatformMessages(array $messageClasses): array
    {
        /** @var array<string, class-string<ApiPlatformMessage>> $result */
        $result =  array_filter(
            $messageClasses,
            static fn ($messageClass) => (new ReflectionClass($messageClass))
                ->implementsInterface(ApiPlatformMessage::class)
        );

        return $result;
    }
}
