<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\OrderFilterInterface;
use Closure;
use EventEngine\DocumentStore\Filter\Filter;

use function array_column;
use function array_multisort;

use const SORT_ASC;
use const SORT_DESC;

final class InMemoryFilterConverter extends FilterConverter
{
    /**
     * @param array<mixed> $apiPlatformFilters
     */
    public function order(array $apiPlatformFilters): Closure
    {
        if (
            ! isset($apiPlatformFilters[$this->orderParameterName])
            || empty($apiPlatformFilters[$this->orderParameterName])
        ) {
            return static fn (array $items) => $items;
        }

        return function (array $items) use ($apiPlatformFilters) {
            $arguments = [];

            foreach ($apiPlatformFilters[$this->orderParameterName] as $orderParameter => $sorting) {
                $arguments += [
                    array_column($items, $orderParameter),
                    $sorting === OrderFilterInterface::DIRECTION_ASC
                        ? SORT_ASC
                        : SORT_DESC,
                ];
            }

            $arguments[] = $items;

            return array_multisort(...$arguments);
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
