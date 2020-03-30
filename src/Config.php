<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Config as EventEngineConfig;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use function array_filter;
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
                $apiPlatformMapping = [];

                $this->commandMapping($config, $apiPlatformMapping);
                $this->queryMapping($config, $apiPlatformMapping);

                return $apiPlatformMapping;
            }
        );
    }

    /**
     * @param array<mixed> $config
     * @param array<array<array<string>>> $mapping
     */
    private function commandMapping(array $config, array &$mapping) : void
    {
        $commandsConfig = $config['compiledCommandRouting'];

        $apiPlatformCommandsConfig = array_filter(
            $commandsConfig,
            static function (array $config) {
                $command = $config['commandName'];
                $reflectionClass = new ReflectionClass($command);

                return $reflectionClass->implementsInterface(ApiPlatformMessage::class)
                    && $command::operationType() !== null
                    && $command::operationName() !== null;
            }
        );

        $mapping = array_reduce(
            $apiPlatformCommandsConfig,
            function (array $mapping, array $config) {
                /** @var class-string $command */
                $command = $config['commandName'];

                $entity = $command::entity() ?? $config['aggregateType'];
                /** @var string $operationType */
                $operationType = $command::operationType();
                /** @var string $operationName */
                $operationName = $command::operationName();

                return $this->addToMapping($mapping, $entity, $operationType, $operationName, $command);
            },
            $mapping
        );
    }

    /**
     * @param array<mixed> $config
     * @param array<array<array<string>>> $mapping
     */
    private function queryMapping(array $config, array &$mapping) : void
    {
        $queriesConfig = $config['compiledQueryDescriptions'];

        $apiPlatformQueriesConfig = array_filter(
            $queriesConfig,
            static function (array $config) {
                $query = $config['name'];
                $reflectionClass = new ReflectionClass($query);

                return $reflectionClass->implementsInterface(ApiPlatformMessage::class)
                    && $query::operationType() !== null
                    && $query::operationName() !== null
                    && $query::entity() !== null;
            }
        );

        $mapping = array_reduce(
            $apiPlatformQueriesConfig,
            function (array $mapping, array $config) {
                /** @var class-string $query */
                $query = $config['name'];
                /** @var class-string $entity */
                $entity = $query::entity();
                /** @var string $operationType */
                $operationType = $query::operationType();
                /** @var string $operationName */
                $operationName = $query::operationName();

                return $this->addToMapping($mapping, $entity, $operationType, $operationName, $query);
            },
            $mapping
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
