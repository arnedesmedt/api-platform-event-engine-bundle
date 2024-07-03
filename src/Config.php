<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Config as EventEngineConfig;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    /** @var array<string, array<string, class-string>>|null */
    private array|null $messageMapping = null;
    /** @var array<class-string, array<int, array<string, mixed>>>|null */
    private array|null $operationMapping = null;

    public function __construct(
        private EventEngineConfig $config,
        #[Autowire('@event_engine.cache')]
        private AbstractAdapter $cache,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /** @return array<string, array<string, class-string>> */
    public function messageMapping(): array
    {
        if ($this->isDevEnv()) {
            return $this->getMessageMapping();
        }

        /** @var array<string, array<string, class-string>> $result */
        $result = $this->cache->get(
            self::API_PLATFORM_MAPPING,
            fn () => $this->getMessageMapping()
        );

        return $result;
    }

    /** @return array<class-string, array<int, array<string, mixed>>> */
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
     * @param array<string, array<string, class-string>> $messageConfig
     *
     * @return array<string, array<string, class-string>>
     */
    private function specificMessageMapping(array $messageConfig, string|null $classKey = null): array
    {
        $apiPlatformMessageConfig = array_filter(
            $messageConfig,
            static function ($config) use ($classKey) {
                /** @var class-string $message */
                $message = $classKey === null ? $config : $config[$classKey];
                $reflectionClass = new ReflectionClass($message);

                return $reflectionClass->implementsInterface(ApiPlatformMessage::class);
            },
        );

        return array_reduce(
            $apiPlatformMessageConfig,
            static function (array $mapping, $config) use ($classKey) {
                /** @var class-string<ApiPlatformMessage> $apiPlatformMessage */
                $apiPlatformMessage = $classKey === null ? $config : $config[$classKey];

                $resource = $apiPlatformMessage::__resource();
                $operationId = $apiPlatformMessage::__operationId();

                if (! isset($mapping[$resource])) {
                    $mapping[$resource] = [];
                }

                $mapping[$resource][$operationId] = $apiPlatformMessage;

                return $mapping;
            },
            [],
        );
    }

    public function clear(string $cacheDir): void
    {
        $this->cache->clear();
    }

    /** @return array<string, array<string, class-string>> */
    private function getMessageMapping(): array
    {
        if (is_array($this->messageMapping)) {
            return $this->messageMapping;
        }

        $config = $this->config->config();

        /** @var array<string, array<string, class-string>> $compiledCommandRouting */
        $compiledCommandRouting = $config['compiledCommandRouting'];
        $commandMapping = $this->specificMessageMapping($compiledCommandRouting, 'commandName');

        /** @var array<string, array<string, class-string>> $commandControllers */
        $commandControllers = array_keys($config['commandControllers']);
        $controllerMapping = $this->specificMessageMapping($commandControllers);

        /** @var array<string, array<string, class-string>> $compiledQueryDescriptions */
        $compiledQueryDescriptions = $config['compiledQueryDescriptions'];
        $queryMapping = $this->specificMessageMapping($compiledQueryDescriptions, 'name');

        $this->messageMapping = array_merge_recursive($commandMapping, $controllerMapping, $queryMapping);

        return $this->messageMapping;
    }

    /** @return array<class-string, array<int, array<string, mixed>>> */
    private function getOperationMapping(): array
    {
        if (is_array($this->operationMapping)) {
            return $this->operationMapping;
        }

        $apiPlatformMapping = $this->messageMapping();

        /** @var array<class-string, array<int, array<string, mixed>>> $operationMapping */
        $operationMapping = [];

        foreach ($apiPlatformMapping as $resource => $operations) {
            foreach ($operations as $operationId => $messageClass) {
                if (! isset($operationMapping[$messageClass])) {
                    $operationMapping[$messageClass] = [];
                }

                $operationMapping[$messageClass][] = [
                    'resource' => $resource,
                    'operationId' => $operationId,
                ];
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
