<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ApiPlatform\Metadata\HttpOperation;

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
        /** @var HttpOperation $operation */
        $operation = $context['operation'];

        return $this->byOperation($operation);
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
    public function byOperation(HttpOperation $operation): string
    {
        return self::byConfigAndOperation($this->config, $operation);
    }

    public static function byConfigAndOperation(
        Config $config,
        HttpOperation $operation,
    ): string {
        $mapping = $config->messageMapping();
        $resourceClass = $operation->getClass();
        $operationName = $operation->getName();

        if (! isset($mapping[$resourceClass][$operationName])) {
            throw FinderException::noMessageFound($operation->getName());
        }

        return $mapping[$resourceClass][$operationName];
    }
}
