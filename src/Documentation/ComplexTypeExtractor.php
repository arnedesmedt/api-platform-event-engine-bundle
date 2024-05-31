<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use function addslashes;
use function assert;
use function is_string;
use function preg_match;
use function preg_quote;
use function sprintf;

class ComplexTypeExtractor
{
    public static function complexTypeWanted(): bool
    {
        return ! empty(self::complexTypeMatch());
    }

    public static function complexTypeMatch(): string|null
    {
        return $_GET['complex'] ?? $_SERVER['complex'] ?? null;
    }

    public static function isClassComplexType(string|null $className): bool
    {
        if ($className === null) {
            return false;
        }

        if (! self::complexTypeWanted()) {
            return false;
        }

        /** @var string $complexTypeMatch */
        $complexTypeMatch = self::complexTypeMatch();

        return (bool) preg_match(
            sprintf('#%s#', preg_quote($complexTypeMatch, '#')),
            $className,
        );
    }

    public static function complexType(string|null $className): string|null
    {
        if (! self::isClassComplexType($className)) {
            return null;
        }

        assert(is_string($className));

        return '\\' . addslashes($className);
    }
}
