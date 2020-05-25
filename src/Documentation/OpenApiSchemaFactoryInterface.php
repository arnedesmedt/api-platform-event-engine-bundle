<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface OpenApiSchemaFactoryInterface
{
    /**
     * @return array<mixed>
     */
    public function create() : array;
}
