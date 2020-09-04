<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface OpenApiSchemaFactoryInterface
{
    /**
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * @param array<string, string> $tags
     *
     * @return array<array<string, string>>
     */
    public function createTags(array $tags): array;

    /**
     * @return array<string>
     */
    public function hideTags(): array;
}
