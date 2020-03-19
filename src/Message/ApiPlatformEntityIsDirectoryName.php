<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use function array_pop;
use function count;
use function explode;
use function implode;
use function sprintf;

trait ApiPlatformEntityIsDirectoryName
{
    public static function entity() : ?string
    {
        $parts = explode('\\', static::class);
        array_pop($parts);
        array_pop($parts);
        $namespace = implode('\\', $parts);

        return sprintf('%s\\%s', $namespace, $parts[count($parts) - 1]);
    }
}
