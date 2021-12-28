<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\SubresourceQuery;
use ADS\Bundle\EventEngineBundle\Config as EventEngineConfig;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Bridge\Symfony\Routing\RouteNameGenerator;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

use function array_filter;
use function array_keys;
use function array_merge_recursive;
use function array_reduce;
use function is_array;
use function preg_match;
use function sprintf;
use function strlen;
use function substr;

final class Config implements CacheClearerInterface
{
    public const API_PLATFORM_MAPPING = 'apiPlatformMapping';
    public const OPERATION_MAPPING = 'operationMapping';
    public const OPERATION_ID_MAPPING = 'operationIdMapping';

    /** @var array<string, array<string, array<string, class-string>>>|null */
    private ?array $messageMapping = null;
    /** @var array<class-string, array<int, array<string, mixed>>>|null */
    private ?array $operationMapping = null;
    /** @var array<string, class-string>|null */
    private ?array $operationIdMapping = null;

    public function __construct(
        private EventEngineConfig $config,
        private AbstractAdapter $cache,
        private string $environment
    ) {
    }

    /**
     * @return array<string, array<string, array<string, class-string>>>
     */
    public function messageMapping(): array
    {
        if ($this->isDevEnv()) {
            return $this->getMessageMapping();
        }

        /** @var array<string, array<string, array<string, class-string>>> $result */
        $result = $this->cache->get(
            self::API_PLATFORM_MAPPING,
            fn () => $this->getMessageMapping()
        );

        return $result;
    }

    /**
     * @return array<class-string, array<int, array<string, mixed>>>
     */
    public function operationMapping(): array
    {
        if ($this->isDevEnv()) {
            return $this->getOperationMapping();
        }

        /** @var array<class-string, array<int, array<string, mixed>>> $result */
        $result = $this->cache->get(
            self::OPERATION_MAPPING,
            fn () => $this->getOperationMapping()
        );

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function operationIdMapping(): array
    {
        if ($this->isDevEnv()) {
            return $this->getOperationIdMapping();
        }

        /** @var array<string, string> $result */
        $result = $this->cache->get(
            self::OPERATION_ID_MAPPING,
            fn () => $this->getOperationIdMapping()
        );

        return $result;
    }

    /**
     * @param array<string, array<string, array<string, class-string>>> $messageConfig
     * @param class-string|null $classKey
     *
     * @return array<string, array<string, array<string, class-string>>>
     */
    private function specificMessageMapping(array $messageConfig, ?string $classKey = null): array
    {
        $apiPlatformMessageConfig = array_filter(
            $messageConfig,
            static function ($config) use ($classKey) {
                $message = $classKey === null ? $config : $config[$classKey];
                $reflectionClass = new ReflectionClass($message);

                return $reflectionClass->implementsInterface(ApiPlatformMessage::class);
            }
        );

        return array_reduce(
            $apiPlatformMessageConfig,
            function (array $mapping, $config) use ($classKey) {
                /** @var class-string<ApiPlatformMessage> $message */
                $message = $classKey === null ? $config : $config[$classKey];

                $entity = $message::__entity();
                $operationType = $message::__operationType();
                $operationName = $message::__operationName();

                return $this->addToMapping($mapping, $entity, $operationType, $operationName, $message);
            },
            []
        );
    }

    /**
     * @param array<string, array<string, array<string, class-string>>> $mapping
     * @param class-string $apiPlatformMessage
     *
     * @return array<string, array<string, array<string, class-string>>>
     */
    private function addToMapping(
        array $mapping,
        string $entity,
        string $type,
        string $name,
        string $apiPlatformMessage
    ): array {
        if (! isset($mapping[$entity])) {
            $mapping[$entity] = [];
        }

        if (! isset($mapping[$entity][$type])) {
            $mapping[$entity][$type] = [];
        }

        $mapping[$entity][$type][$name] = $apiPlatformMessage;

        $reflectionClass = new ReflectionClass($apiPlatformMessage);

        if ($reflectionClass->implementsInterface(SubresourceQuery::class)) {
            /** @var string $entity */
            $entity = $apiPlatformMessage::__rootResourceClass();
            $entityName = StringUtil::entityNameFromClassName($entity);
            $prefix = sprintf(
                '%s%s',
                RouteNameGenerator::ROUTE_NAME_PREFIX,
                RouteNameGenerator::inflector($entityName, $apiPlatformMessage::__subresourceIsCollection())
            );

            $mapping[$entity][$type][substr($name, strlen($prefix) + 1)] = $apiPlatformMessage;
        }

        return $mapping;
    }

    public function clear(string $cacheDir): void
    {
        $this->cache->clear();
    }

    /**
     * @return array<string, array<string, array<string, class-string>>>
     */
    private function getMessageMapping(): array
    {
        if (is_array($this->messageMapping)) {
            return $this->messageMapping;
        }

        $config = $this->config->config();
        $commandMapping = $this->specificMessageMapping(
            $config['compiledCommandRouting'],
            'commandName'
        );

        $controllerMapping = $this->specificMessageMapping(array_keys($config['commandControllers']));

        $queryMapping = $this->specificMessageMapping(
            $config['compiledQueryDescriptions'],
            'name'
        );

        $this->messageMapping = array_merge_recursive($commandMapping, $controllerMapping, $queryMapping);

        return $this->messageMapping;
    }

    /**
     * @return array<class-string, array<int, array<string, mixed>>>
     */
    private function getOperationMapping(): array
    {
        if (is_array($this->operationMapping)) {
            return $this->operationMapping;
        }

        $apiPlatformMapping = $this->messageMapping();

        /** @var array<class-string, array<int, array<string, mixed>>> $operationMapping */
        $operationMapping = [];

        foreach ($apiPlatformMapping as $resource => $operationTypes) {
            foreach ($operationTypes as $operationType => $messageClasses) {
                foreach ($messageClasses as $operationName => $messageClass) {
                    if (! isset($operationMapping[$messageClass])) {
                        $operationMapping[$messageClass] = [];
                    }

                    $operationMapping[$messageClass][] = [
                        'resource' => $resource,
                        'operationType' => $operationType,
                        'operationName' => $operationName,
                        'operationId' => $messageClass::__operationId(),
                    ];
                }
            }
        }

        $this->operationMapping = $operationMapping;

        return $this->operationMapping;
    }

    /**
     * @return array<string, class-string>
     */
    private function getOperationIdMapping(): array
    {
        if (is_array($this->operationIdMapping)) {
            return $this->operationIdMapping;
        }

        $operationMapping = $this->operationMapping();

        /** @var array<string, class-string> $operationIdMapping */
        $operationIdMapping = [];

        foreach ($operationMapping as $operationClass => $operations) {
            foreach ($operations as $operation) {
                /** @var string $operationId */
                $operationId = $operation['operationId'];
                $operationIdMapping[$operationId] = $operationClass;
            }
        }

        $this->operationIdMapping = $operationIdMapping;

        return $this->operationIdMapping;
    }

    private function isDevEnv(): bool
    {
        return preg_match('/(dev(.*)|local)/i', $this->environment) === 1;
    }
}
