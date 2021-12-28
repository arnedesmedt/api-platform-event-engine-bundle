<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;

use function sprintf;

final class Finder
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return string|class-string
     */
    public function byContext(array $context): string
    {
        /** @var string $entity */
        $entity = $context['resource_class'];
        /** @var string $operationType */
        $operationType = $context['operation_type'];
        /** @var string $operationName */
        $operationName = $context[sprintf('%s_operation_name', $operationType)];

        return $this->byResourceAndOperation($entity, $operationType, $operationName);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasMessageByContext(array $context): bool
    {
        try {
            $this->byContext($context);

            return true;
        } catch (FinderException) {
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
