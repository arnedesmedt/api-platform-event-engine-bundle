<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Config as EventEngineConfig;
use EventEngine\Data\ImmutableRecord;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

use function array_filter;
use function array_keys;
use function array_merge_recursive;
use function array_reduce;
use function is_array;
use function preg_match;

final class Config implements CacheClearerInterface
{
    public const API_PLATFORM_MAPPING = 'apiPlatformMapping';
    public const OPERATION_MAPPING = 'operationMapping';

    private EventEngineConfig $config;
    private AbstractAdapter $cache;
    private string $environment;

    /** @var array<string, array<string, array<string, class-string<ImmutableRecord>>>>|null */
    private ?array $messageMapping = null;
    /** @var array<class-string<ImmutableRecord>, array<string, string>>|null */
    private ?array $operationMapping = null;

    public function __construct(EventEngineConfig $config, AbstractAdapter $cache, string $environment)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->environment = $environment;
    }

    /**
     * @return array<string, array<string, array<string, class-string<ImmutableRecord>>>>
     */
    public function messageMapping(): array
    {
        if ($this->isDevEnv()) {
            return $this->getMessageMapping();
        }

        return $this->cache->get(
            self::API_PLATFORM_MAPPING,
            function () {
                return $this->getMessageMapping();
            }
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function operationMapping(): array
    {
        if ($this->isDevEnv()) {
            return $this->getOperationMapping();
        }

        return $this->cache->get(
            self::OPERATION_MAPPING,
            function () {
                return $this->getOperationMapping();
            }
        );
    }

    /**
     * @param array<mixed> $messageConfig
     *
     * @return array<string, array<string, array<string, class-string<ImmutableRecord>>>>
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
     * @param array<string, array<string, array<string, class-string<ImmutableRecord>>>> $mapping
     * @param class-string<ImmutableRecord> $apiPlatformMessage
     *
     * @return array<string, array<string, array<string, class-string<ImmutableRecord>>>>
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

        return $mapping;
    }

    public function clear(string $cacheDir): void
    {
        $this->cache->clear();
    }

    /**
     * @return array<string, array<string, array<string, class-string<ImmutableRecord>>>>
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
     * @return array<class-string<ImmutableRecord>, array<string, string>>
     */
    private function getOperationMapping(): array
    {
        if (is_array($this->operationMapping)) {
            return $this->operationMapping;
        }

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

        $this->operationMapping = $operationMapping;

        return $this->operationMapping;
    }

    private function isDevEnv(): bool
    {
        return preg_match('/(dev(.*)|local)/i', $this->environment) === 1;
    }
}
