<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use Exception;

use function sprintf;

final class FinderException extends Exception
{
    /**
     * @return static
     */
    public static function noMessageFound(string $resource, ?string $operationType, ?string $operationName)
    {
        return new static(
            sprintf(
                'Could not find an event engine message that is mapped with the API platform call ' .
                '(resource: \'%s\', operation type: \'%s\', operation name: \'%s\').',
                $resource,
                $operationType,
                $operationName
            )
        );
    }
}
