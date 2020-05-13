<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Util;

use Closure;
use stdClass;
use function is_array;
use function is_int;

final class ArrayUtil
{
    /**
     * @param array<int|string, mixed> $array
     *
     * @return array<int|string, mixed>
     */
    private static function process(
        array $array,
        ?Closure $keyClosure = null,
        ?Closure $valueClosure = null,
        bool $recursive = false
    ) : array {
        $processedArray = [];

        foreach ($array as $key => $value) {
            if ($recursive) {
                $isStdClass = $value instanceof stdClass;

                if ($isStdClass) {
                    $value = (array) $value;
                }

                if (is_array($value)) {
                    $value = self::process($value, $keyClosure, $valueClosure, $recursive);
                }

                $value = $isStdClass ? (object) $value : $value;
            }

            $processedArray[$keyClosure ? $keyClosure($key) : $key] = $valueClosure ? $valueClosure($value) : $value;
        }

        return $processedArray;
    }

    /**
     * @param array<int|string, mixed> $array
     *
     * @return array<int|string, mixed>
     */
    public static function toCamelCasedKeys(array $array, bool $recursive = false) : array
    {
        return self::process(
            $array,
            static fn($key) => is_int($key) ? $key : StringUtil::camelize($key),
            null,
            $recursive
        );
    }

    /**
     * @param array<int|string, mixed> $array
     *
     * @return array<int|string, mixed>
     */
    public static function toSnakeCasedKeys(array $array, bool $recursive = false) : array
    {
        return self::process(
            $array,
            static fn($key) => is_int($key) ? $key : StringUtil::decamilize($key),
            null,
            $recursive
        );
    }
}
