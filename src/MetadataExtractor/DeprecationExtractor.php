<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\MetadataExtractor;

use ADS\Util\MetadataExtractor\AttributeExtractor;
use JetBrains\PhpStorm\Deprecated;

class DeprecationExtractor
{
    public function __construct(
        private readonly AttributeExtractor $attributeExtractor,
    ) {
    }

    public function reasonFromReflectionClass(\ReflectionClass $reflectionClass): string|null
    {
        $attribute = $this->attributeExtractor
            ->attributeFromReflectionClassAndAttribute(
                $reflectionClass,
                Deprecated::class,
            );

        if ($attribute === null) {
            return null;
        }

        return $attribute->getArguments()[0] ?? 'Deprecated';
    }
}