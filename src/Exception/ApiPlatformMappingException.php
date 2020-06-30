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
    public static function noOperationFound(string $class): self
    {
        return new self(
            sprintf(
                'No api-platform operation found for message \'%s\'.',
                $class
            )
        );
    }

    /**
     * @param class-string $class
     */
    public static function noOperationTypeFound(string $class): self
    {
        return new self(
            sprintf(
                'No api-platform operation type found for message \'%s\'.',
                $class
            )
        );
    }

    /**
     * @param class-string $class
     */
    public static function noEntityFound(string $class): self
    {
        return new self(
            sprintf(
                'No api-platform entity found for message \'%s\'.',
                $class
            )
        );
    }
}
