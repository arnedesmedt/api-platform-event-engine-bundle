<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource\ChangeApiResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ReflectionClass;
use RuntimeException;
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
    public function byContext(array $context) : string
    {
        $entity = $context['resource_class'];
        $reflectionClass = new ReflectionClass($entity);

        if ($reflectionClass->implementsInterface(ChangeApiResource::class)) {
            $entity = $entity::__newApiResource();
        }

        $operationType = $context['operation_type'];
        $operationName = $context[sprintf('%s_operation_name', $operationType)];

        $mapping = $this->config->apiPlatformMapping();

        if (! isset($mapping[$entity][$operationType][$operationName])) {
            throw new RuntimeException(
                sprintf(
                    'Could not find an event engine message that is mapped with the API platform call ' .
                    '(resource: \'%s\', operation type: \'%s\', operation name: \'%s\').',
                    $entity,
                    $operationType,
                    $operationName
                )
            );
        }

        return $mapping[$entity][$operationType][$operationName];
    }
}
