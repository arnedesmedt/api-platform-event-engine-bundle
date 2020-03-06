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
            static function (array $mapping, array $config) {
                /** @var ApiPlatformMessage $command */
                $command = $config['commandName'];
                $aggregateType = $config['aggregateType'];

                $operationType = $command::operationType();
                $operationName = $command::operationName();

                if (! isset($mapping[$aggregateType])) {
                    $mapping[$aggregateType] = [];
                }

                if (! isset($mapping[$aggregateType][$operationType])) {
                    $mapping[$aggregateType][$operationType] = [];
                }

                $mapping[$aggregateType][$operationType][$operationName] = $command;

                return $mapping;
            },
            $mapping
        );
    }

    public function clear(string $cacheDir) : void
    {
        $this->cache->clear();
    }
}
