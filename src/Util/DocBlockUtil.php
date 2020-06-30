<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Util;

use phpDocumentor\Reflection\DocBlock;

use function array_filter;
use function implode;

final class DocBlockUtil
{
    public static function summaryAndDescription(DocBlock $docBlock): string
    {
        return implode(
            '<br/>',
            array_filter(
                [
                    $docBlock->getSummary(),
                    $docBlock->getDescription()->render(),
                ],
                static function ($part) {
                    return $part !== '';
                }
            )
        );
    }
}
