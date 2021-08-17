<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resource;

interface ChangeIdentifierResource
{
    /**
     * @param array<string, mixed> $identifiers
     *
     * @return array<string, mixed>
     */
    public static function changeIdentifiers(array $identifiers): array;
}
