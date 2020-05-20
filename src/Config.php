<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Config as EventEngineConfig;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use function array_filter;
use function array_merge_recursive;
use function array_reduce;

final class Config implements CacheClearerInterface
{
    public const API_PLATFORM_MAPPING = 'apiPlatformMapping';

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
    public function apiPlatformMapping() : array
    {
        return $this->cache->get(
            self::API_PLATFORM_MAPPING,
            function () {
                $config = $this->config->config();
                $commandMapping = $this->messageMapping(
                    $config['compiledCommandRouting'],
                    'commandName'
                );

                $queryMapping = $this->messageMapping(
                    $config['compiledQueryDescriptions'],
                    'name'
                );

                return array_merge_recursive($commandMapping, $queryMapping);
            }
        );
    }

    /**
     * @param array<mixed> $messageConfig
     *
     * @return array<mixed>
     */
    private function messageMapping(array $messageConfig, string $classKey) : array
    {
        $apiPlatformMessageConfig = array_filter(
            $messageConfig,
            static function (array $config) use ($classKey) {
                $message = $config[$classKey];
                $reflectionClass = new ReflectionClass($message);

                return $reflectionClass->implementsInterface(ApiPlatformMessage::class);
            }
        );

        return array_reduce(
            $apiPlatformMessageConfig,
            function (array $mapping, array $config) use ($classKey) {
                /** @var class-string $message */
                $message = $config[$classKey];

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
    ) : array {
        if (! isset($mapping[$entity])) {
            $mapping[$entity] = [];
        }

        if (! isset($mapping[$entity][$type])) {
            $mapping[$entity][$type] = [];
        }

        $mapping[$entity][$type][$name] = $apiPlatformMessage;

        return $mapping;
    }

    public function clear(string $cacheDir) : void
    {
        $this->cache->clear();
    }
}
