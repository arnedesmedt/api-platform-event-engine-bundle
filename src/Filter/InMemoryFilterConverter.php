<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Metadata\Operation;
use Closure;
use LogicException;

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
use function explode;
use function preg_match;
use function preg_quote;
use function sprintf;
use function strtoupper;

use const SORT_ASC;
use const SORT_DESC;

final class InMemoryFilterConverter extends FilterConverter
{
    /** @inheritDoc */
    public function order(array $filters): Closure|null
    {
        if (
            ! isset($filters[$this->orderParameterName])
            || empty($filters[$this->orderParameterName])
        ) {
            return null;
        }

        /** @var array<string, string> $orderParameters */
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
                    ],
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
                    $items,
                ),
            );
        };
    }

    /** @inheritDoc */
    public function filter(array $filters, Operation $operation, string $resourceClass): Closure|null
    {
        $searchFilter = ($this->filterFinder)($operation, SearchFilter::class);

        if ($searchFilter === null) {
            return null;
        }

        $descriptions = $searchFilter->getDescription($resourceClass);
        /** @var array<string, string> $filters */
        $filters = array_intersect_key($filters, $descriptions);

        return static function (array $items) use ($filters) {
            $itemArrays = array_map(static fn ($item) => $item->toArray(), $items);

            foreach ($filters as $filter => $value) {
                $filterParts = explode('.', $filter);

                $itemArrays = array_filter(
                    $itemArrays,
                    static function (array $item) use ($filter, $filterParts, $value): bool {
                        foreach ($filterParts as $filterPart) {
                            if (! isset($item[$filterPart])) {
                                throw new LogicException(
                                    sprintf(
                                        'Property \'%s\' not found in item.',
                                        $filter,
                                    ),
                                );
                            }

                            $item = $item[$filterPart];
                        }

                        return (bool) preg_match(
                            sprintf('#.*%s.*#', preg_quote($value, '#')),
                            $item,
                        );
                    },
                );
            }

            return array_values(
                array_intersect_key(
                    $items,
                    array_flip(array_keys($itemArrays)),
                ),
            );
        };
    }
}
