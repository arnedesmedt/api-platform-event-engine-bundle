<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;

use function sprintf;

final class Finder
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param array<mixed> $context
     *
     * @return string|class-string
     */
    public function byContext(array $context): string
    {
        $entity = $context['resource_class'];
        $operationType = $context['operation_type'];
        $operationName = $context[sprintf('%s_operation_name', $operationType)];

        return $this->byResourceAndOperation($entity, $operationType, $operationName);
    }

    /**
     * @param array<mixed> $context
     */
    public function hasMessageByContext(array $context): bool
    {
        try {
            $this->byContext($context);

            return true;
        } catch (FinderException $exception) {
            return false;
        }
    }

    /**
     * @return string|class-string
     */
    public function byResourceAndOperation(string $resourceClass, string $operationType, string $operationName): string
    {
        return self::byConfigResourceAndOperation($this->config, $resourceClass, $operationType, $operationName);
    }

    public static function byConfigResourceAndOperation(
        Config $config,
        string $resourceClass,
        string $operationType,
        string $operationName
    ): string {
        $mapping = $config->messageMapping();

        if (! isset($mapping[$resourceClass][$operationType][$operationName])) {
            throw FinderException::noMessageFound($resourceClass, $operationType, $operationName);
        }

        return $mapping[$resourceClass][$operationType][$operationName];
    }
}
