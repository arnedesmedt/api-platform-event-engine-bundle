<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Config as EventEngineConfig;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

use function array_filter;
use function array_keys;
use function array_merge_recursive;
use function array_reduce;

final class Config implements CacheClearerInterface
{
    public const API_PLATFORM_MAPPING = 'apiPlatformMapping';
    public const OPERATION_MAPPING = 'operationMapping';

    private EventEngineConfig $config;
    private AbstractAdapter $cache;

    public function __construct(EventEngineConfig $config, AbstractAdapter $cache)
    {
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * @return array<array<array<string>>>
     */
    public function messageMapping(): array
    {
        return $this->cache->get(
            self::API_PLATFORM_MAPPING,
            function () {
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

                return array_merge_recursive($commandMapping, $controllerMapping, $queryMapping);
            }
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function operationMapping(): array
    {
        return $this->cache->get(
            self::OPERATION_MAPPING,
            function () {
                $apiPlatformMapping = $this->messageMapping();

                $operationMapping = [];

                foreach ($apiPlatformMapping as $resource => $operationTypes) {
                    foreach ($operationTypes as $operationType => $messageClasses) {
                        foreach ($messageClasses as $operationName => $messageClass) {
                            $operationMapping[$messageClass] = [
                                'resource' => $resource,
                                'operationType' => $operationType,
                                'operationName' => $operationName,
                            ];
                        }
                    }
                }

                return $operationMapping;
            }
        );
    }

    /**
     * @param array<mixed> $messageConfig
     *
     * @return array<mixed>
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
                /** @var class-string $message */
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
     * @param array<array<array<string>>> $mapping
     * @param class-string|string $apiPlatformMessage
     *
     * @return array<array<array<string>>>
     */
    private function addToMapping(
        array $mapping,
        string $entity,
        string $type,
        string $name,
        $apiPlatformMessage
    ): array {
        if (! isset($mapping[$entity])) {
            $mapping[$entity] = [];
        }

        if (! isset($mapping[$entity][$type])) {
            $mapping[$entity][$type] = [];
        }

        $mapping[$entity][$type][$name] = $apiPlatformMessage;

        return $mapping;
    }

    public function clear(string $cacheDir): void
    {
        $this->cache->clear();
    }
}
