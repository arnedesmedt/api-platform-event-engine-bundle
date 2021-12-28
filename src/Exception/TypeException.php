<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use Exception;

use function sprintf;

final class TypeException extends Exception
{
    /**
     * @return static
     */
    public static function noToMethodForSchemaType(string $schemaType): static
    {
        return new static(
            sprintf(
                'No \'toMethod\' implemented for schema type \'%s\'.',
                $schemaType
            )
        );
    }
}
