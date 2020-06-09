<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Util;

use function floatval;
use function intval;
use function is_numeric;
use function strpos;

final class Util
{
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
}
