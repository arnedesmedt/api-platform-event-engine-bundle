<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use Exception;
use function sprintf;

final class ClassException extends Exception
{
    /**
     * @return static
     */
    public static function fullQualifiedClassNameWithoutBackslash(string $className)
    {
        return new static(
            sprintf(
                'Could not find a \\ in the full qualified class name \'%s\'.',
                $className
            )
        );
    }
}
