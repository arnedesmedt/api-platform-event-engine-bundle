<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\EventEngineBundle\Util;
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
    public function byContext(array $context, bool $resourceClassHasLinkedAggregate = true) : string
    {
        $resourceClass = $context['resource_class'];

        if ($resourceClassHasLinkedAggregate) {
            $resourceClass = Util::fromStateToAggregateClass($resourceClass);
        }

        $operationType = $context['operation_type'];
        $operationName = $context[sprintf('%s_operation_name', $operationType)];

        $mapping = $this->config->apiPlatformMapping();

        if (! isset($mapping[$resourceClass][$operationType][$operationName])) {
            throw new RuntimeException(
                sprintf(
                    'Could not find an event engine message that is mapped with the API platform call ' .
                    '(resource: \'%s\', operation type: \'%s\', operation name: \'%s\').',
                    $resourceClass,
                    $operationType,
                    $operationName
                )
            );
        }

        return $mapping[$resourceClass][$operationType][$operationName];
    }
}
