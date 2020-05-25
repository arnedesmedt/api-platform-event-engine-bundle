<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use function array_keys;
use function array_map;
use function array_values;

trait OpenApiSchemaLogic
{
    /**
     * @inheritDoc
     */
    public function createTags(array $tags) : array
    {
        return array_map(
            static function ($name, $description) {
                return [
                    'name' => $name,
                    'description' => $description,
                ];
            },
            array_keys($tags),
            array_values($tags)
        );
    }
}
