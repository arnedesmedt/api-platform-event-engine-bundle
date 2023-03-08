<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use Exception;

use function sprintf;

final class FinderException extends Exception
{
    /** @return static */
    public static function noMessageFound(string|null $operationName): static
    {
        return new static(
            sprintf(
                'Could not find an event engine message that is mapped with the API platform operation \'%s\'.',
                $operationName ?? 'No operation name found',
            ),
        );
    }
}
