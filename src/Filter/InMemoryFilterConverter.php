<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ADS\Util\ArrayUtil;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use Closure;

use function array_column;
use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function array_multisort;
use function array_pop;
use function array_replace;
use function array_values;
use function preg_match;
use function preg_quote;
use function sprintf;
use function strtoupper;

use const SORT_ASC;
use const SORT_DESC;

final class InMemoryFilterConverter extends FilterConverter
{
    /**
     * @inheritDoc
     */
    public function order(array $filters): ?Closure
    {
        if (
            ! isset($filters[$this->orderParameterName])
            || empty($filters[$this->orderParameterName])
        ) {
            return null;
        }

        $orderParameters = $filters[$this->orderParameterName];

        return static function (array $items) use ($orderParameters) {
            $itemArrays = array_map(static fn ($item) => $item->toArray(), $items);
            $itemKeys = array_keys($itemArrays);
            $arguments = [];

            foreach ($orderParameters as $orderParameter => $sorting) {
                $arguments = array_merge(
                    $arguments,
                    [
                        array_column($itemArrays, $orderParameter),
                        strtoupper($sorting) === OrderFilterInterface::DIRECTION_ASC
                            ? SORT_ASC
                            : SORT_DESC,
                    ]
                );
            }

            $arguments[] = $itemKeys;

            // @phpstan-ignore-next-line
            array_multisort(...$arguments);

            /** @var array<int|string> $keysSort */
            $keysSort = array_pop($arguments);

            return array_values(
                array_replace(
                    array_flip($keysSort),
                    $items
                )
            );
        };
    }

    /**
     * @inheritDoc
     */
    public function filter(array $filters, string $resourceClass): ?Closure
    {
        $searchFilter = ($this->filterFinder)($resourceClass, SearchFilter::class);

        if ($searchFilter === null) {
            return null;
        }

        $descriptions = $searchFilter->getDescription($resourceClass);
        /** @var array<string, string> $filters */
        $filters = ArrayUtil::toCamelCasedKeys(array_intersect_key($filters, $descriptions));

        return static function (array $items) use ($filters) {
            $itemArrays = array_map(static fn ($item) => $item->toArray(), $items);

            foreach ($filters as $filter => $value) {
                $itemArrays = array_filter(
                    $itemArrays,
                    static fn (array $item) => (bool) preg_match(
                        sprintf('#.*%s.*#', preg_quote($value, '#')),
                        $item[$filter]
                    )
                );
            }

            return array_values(
                array_intersect_key(
                    $items,
                    array_flip(array_keys($itemArrays))
                )
            );
        };
    }
}
