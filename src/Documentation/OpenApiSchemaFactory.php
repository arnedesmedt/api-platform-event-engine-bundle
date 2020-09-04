<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

final class OpenApiSchemaFactory implements OpenApiSchemaFactoryInterface
{
    use OpenApiSchemaLogic;

    /**
     * @inheritDoc
     */
    public function create(): array
    {
        return ['openapi' => '3.0.3'];
    }

    /**
     * @inheritDoc
     */
    public function hideTags(): array
    {
        return [];
    }
}
