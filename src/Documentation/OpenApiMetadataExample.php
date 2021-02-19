<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

final class OpenApiMetadataExample implements OpenApiMetadata
{
    /**
     * @inheritDoc
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function servers(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function tagOrder(): array
    {
        return [];
    }
}
