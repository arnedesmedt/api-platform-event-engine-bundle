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

        return function (array $items) use ($filters) {
            $arguments = [];

            foreach ($filters[$this->orderParameterName] as $orderParameter => $sorting) {
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
     * @inheritDoc
     */
    public function filter(array $filters, string $resourceClass): ?Filter
    {
        if (! isset($filters[$this->pageParameterName])) {
            return null;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function skip(array $filters): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function limit(array $filters): ?int
    {
        return null;
    }
}
