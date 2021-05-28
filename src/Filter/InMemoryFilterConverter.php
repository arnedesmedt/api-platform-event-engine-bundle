<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\OrderFilterInterface;
use Closure;
use EventEngine\DocumentStore\Filter\Filter;

use function array_column;
use function array_merge;
use function array_multisort;
use function array_pop;
use function strtoupper;

use const SORT_ASC;
use const SORT_DESC;

final class InMemoryFilterConverter extends FilterConverter
{
    /**
     * @param array<mixed> $apiPlatformFilters
     */
    public function order(array $apiPlatformFilters): ?Closure
    {
        if (
            ! isset($apiPlatformFilters[$this->orderParameterName])
            || empty($apiPlatformFilters[$this->orderParameterName])
        ) {
            return null;
        }

        return function (array $items) use ($apiPlatformFilters) {
            $arguments = [];

            foreach ($apiPlatformFilters[$this->orderParameterName] as $orderParameter => $sorting) {
                $arguments = array_merge(
                    $arguments,
                    [
                        array_column($items, $orderParameter),
                        strtoupper($sorting) === OrderFilterInterface::DIRECTION_ASC
                            ? SORT_ASC
                            : SORT_DESC,
                    ]
                );
            }

            $arguments[] = $items;

            // @phpstan-ignore-next-line
            array_multisort(...$arguments);

            return array_pop($arguments);
        };
    }

    /**
     * @param array<mixed> $apiPlatformFilters
     */
    public function filter(array $apiPlatformFilters): ?Filter
    {
        if (! isset($apiPlatformFilters[$this->pageParameterName])) {
            return null;
        }

        return null;
    }

    /**
     * @param array<mixed> $apiPlatformFilters
     */
    public function skip(array $apiPlatformFilters): ?int
    {
        return null;
    }

    /**
     * @param array<mixed> $apiPlatformFilters
     */
    public function limit(array $apiPlatformFilters): ?int
    {
        return null;
    }
}
