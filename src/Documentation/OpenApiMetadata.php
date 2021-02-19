<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ApiPlatform\Core\OpenApi\Model\Server;

interface OpenApiMetadata
{
    /**
     * @return array<Tag>
     */
    public function tags(): array;

    /**
     * @return array<string>
     */
    public static function tagOrder(): array;

    /**
     * @return array<Server>
     */
    public function servers(): array;
}
