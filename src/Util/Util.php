<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Util;

use RuntimeException;

use function array_filter;
use function array_map;
use function array_search;
use function array_slice;
use function explode;
use function floatval;
use function implode;
use function intval;
use function is_numeric;
use function reset;
use function sort;
use function sprintf;
use function strpos;

final class Util
{
    public const ENTITY_PREFIX_NAMES = [
        'Entity',
        'Model',
    ];

    /**
     * @return mixed
     */
    public static function castFromString(string $string)
    {
        switch (true) {
            case $string === 'false':
                return false;

            case $string === 'true':
                return true;

            case is_numeric($string) && strpos($string, '.') !== false:
                return floatval($string);

            case is_numeric($string):
                return intval($string);

            default:
                return $string;
        }
    }

    public static function entityNamespaceFromClassName(string $className): string
    {
        $positions = self::positionsOfPrefixes($className);
        $resourceNameParts = explode('\\', $className);

        if (! empty($positions)) {
            $position = reset($positions);
            $namespaceParts = array_slice($resourceNameParts, 0, $position + 1);

            if (empty($namespaceParts)) {
                return '\\';
            }

            return implode('\\', $namespaceParts);
        }

        throw new RuntimeException(
            sprintf(
                'Entity or Model name not found for class \'%s\'.',
                $className
            )
        );
    }

    public static function entityNameFromClassName(string $className): string
    {
        // First try to find the short name by the class
        // Classes like *\Entity\Bank\* or *\Model\Bank\* will result in short name: Bank.
        $positions = self::positionsOfPrefixes($className);
        $resourceNameParts = explode('\\', $className);

        if (! empty($positions)) {
            foreach ($positions as $position) {
                $shortName = $resourceNameParts[$position + 1] ?? null;

                if ($shortName) {
                    return $shortName;
                }
            }
        }

        throw new RuntimeException(
            sprintf(
                'Entity or Model name not found for class \'%s\'.',
                $className
            )
        );
    }

    /**
     * @param array<string> $prefixes
     *
     * @return array<int>
     */
    private static function positionsOfPrefixes(string $className, array $prefixes = self::ENTITY_PREFIX_NAMES): array
    {
        $resourceNameParts = explode('\\', $className);

        $positions = array_filter(
            array_map(
                static function (string $prefixOfResourceName) use ($resourceNameParts) {
                    $position = array_search($prefixOfResourceName, $resourceNameParts);

                    if ($position === false) {
                        return null;
                    }

                    return $position;
                },
                $prefixes
            )
        );

        sort($positions);

        return $positions;
    }
}
