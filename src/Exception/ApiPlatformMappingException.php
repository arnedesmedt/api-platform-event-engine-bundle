<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use Exception;

use function sprintf;

final class ApiPlatformMappingException extends Exception
{
    /**
     * @param class-string $class
     */
    public static function noOperationNameFound(string $class): self
    {
        return new self(
            sprintf(
                'No api-platform operation name found for message \'%s\'.',
                $class
            )
        );
    }

    /**
     * @param class-string $class
     */
    public static function noResourceFound(string $class): self
    {
        return new self(
            sprintf(
                'No api-platform resource found for message \'%s\'.',
                $class
            )
        );
    }
}
