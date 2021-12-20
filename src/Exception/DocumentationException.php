<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use Exception;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class DocumentationException extends Exception
{
    /**
     * @param array<mixed> $schema
     */
    public static function moreThanOneNullType(array $schema): self
    {
        return new self(
            sprintf(
                'Got JSON Schema type defined as an array with more than one type + NULL set: %s',
                json_encode($schema, JSON_THROW_ON_ERROR)
            )
        );
    }
}
