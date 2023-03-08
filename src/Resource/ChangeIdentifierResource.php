<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resource;

interface ChangeIdentifierResource
{
    /** @return array<string, string> */
    public static function identifierNameMapping(): array;
}
