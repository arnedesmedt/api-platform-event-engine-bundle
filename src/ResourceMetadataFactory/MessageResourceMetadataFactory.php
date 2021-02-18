<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;
use function json_encode;
use function sprintf;
use function strtoupper;
use function ucfirst;

use const ARRAY_FILTER_USE_BOTH;

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

    public function __construct(
        ResourceMetadataFactoryInterface $decorated,
        Config $config
    ) {
        $this->decorated = $decorated;
        $this->config = $config;
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        $messageMapping = $this->config->messageMapping();

        if (! array_key_exists($resourceClass, $messageMapping)) {
            throw new RuntimeException(
                sprintf(
                    'No messages found for resource class \'%s\'. Resources with messages are: %s.',
                    $resourceClass,
                    json_encode(array_keys($messageMapping))
                )
            );
        }

        $resourceMessageMapping = $messageMapping[$resourceClass];

        foreach (OperationType::TYPES as $operationType) {
            $getMethod = sprintf('get%sOperations', ucfirst($operationType));
            $withMethod = sprintf('with%sOperations', ucfirst($operationType));

            $operations = $resourceMetadata->{$getMethod}() ?? [];
            $operations = self::rejectSimpleOperations($operations);
            $messages = self::filterApiPlatformMessages($resourceMessageMapping[$operationType] ?? []);

            $operations = $this
                ->addOperations(
                    $operations ?? [],
                    $messages
                );

            $operations = $this
                ->decorateOperations(
                    $operations,
                    $messages
                );

            $resourceMetadata = $resourceMetadata->{$withMethod}($operations);
        }

        return $resourceMetadata;
    }

    /**
     * @param array<string, array<mixed>> $operations
     *
     * @return array<string, array<mixed>>
     */
    private static function rejectSimpleOperations(array $operations): array
    {
        return array_filter(
            $operations,
            static fn ($operation, $operationName) => ! isset($operation['method'])
                || $operation['method'] !== strtoupper($operationName),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param array<string, class-string> $messageClasses
     *
     * @return array<string, class-string<ApiPlatformMessage>>
     */
    public static function filterApiPlatformMessages(array $messageClasses): array
    {
        /** @var array<string, class-string<ApiPlatformMessage>> $result */
        $result =  array_filter(
            $messageClasses,
            static fn ($messageClass) => (new ReflectionClass($messageClass))
                ->implementsInterface(ApiPlatformMessage::class)
        );

        return $result;
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

        /** @var array<string, array<mixed>> $decoratedOperations */
        $decoratedOperations = array_combine(
            $operationKeys,
            array_map(
                function (string $operationName, $operation) use ($messagesByOperationName) {
                    /** @var class-string<ApiPlatformMessage>|false $messageClass */
                    $messageClass = $messagesByOperationName[$operationName] ?? false;

                    if ($messageClass) {
                        $this->addHttpMethod($operation, $messageClass);
                        $this->addPath($operation, $messageClass);
                        $this->addController($operation, $messageClass);
                        $this->addTags($operation, $messageClass);
                        $this->needRead($operation);
                        $this->addStateless($operation, $messageClass);
                    }

                    return $operation;
                },
                $operationKeys,
                $operations
            )
        );

        return $decoratedOperations;
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
    private function addTags(array &$operation, string $messageClass): void
    {
        if (isset($operation['tags'])) {
            return;
        }

        $operation['tags'] = $messageClass::__tags();
    }

    /**
     * @param array<mixed> $operation
     * @param class-string<ApiPlatformMessage> $messageClass
     */
    private function addStateless(array &$operation, string $messageClass): void
    {
        if (isset($operation['stateless'])) {
            return;
        }

        $operation['stateless'] = $messageClass::__stateless();
    }
}
