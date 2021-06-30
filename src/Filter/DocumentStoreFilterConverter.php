<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\OrderBy\AndOrder;
use EventEngine\DocumentStore\OrderBy\OrderBy;

use function array_keys;
use function array_map;
use function reset;
use function sprintf;
use function ucfirst;

final class DocumentStoreFilterConverter extends FilterConverter
{
    /**
     * @param array<mixed> $filters
     */
    public function order(array $filters): ?OrderBy
    {
        if (
            ! isset($filters[$this->orderParameterName])
            || empty($filters[$this->orderParameterName])
        ) {
            return null;
        }

        $orderProperties = $filters[$this->orderParameterName];

        $orders = array_map(
            static function ($propertyName, $order) {
                $orderClass = sprintf('\EventEngine\DocumentStore\OrderBy\%s', ucfirst($order));

                return $orderClass::fromString(sprintf('state.%s', $propertyName));
            },
            array_keys($orderProperties),
            $orderProperties,
        );

        $order = reset($orders);

        while (! empty($orders)) {
            $order = AndOrder::by(reset($orders), $order);
        }

        return $order;
    }

    /**
     * @param array<mixed> $filters
     */
    public function filter(array $filters): ?Filter
    {
//        if (! isset($filters[$this->pageParameterName])) {
//            return null;
//        }
//
//        return null;

        return null;
    }

    /**
     * @param array<mixed> $filters
     */
    public function skip(array $filters): ?int
    {
        if (
            $this->page($filters) === null
            || $this->itemsPerPage($filters) === null
        ) {
            return null;
        }

        return ($this->page($filters) - 1) * $this->itemsPerPage($filters);
    }

    /**
     * @param array<mixed> $filters
     */
    public function page(array $filters): ?int
    {
        if (! isset($filters[$this->pageParameterName])) {
            return null;
        }

        return (int) $filters[$this->pageParameterName];
    }

    /**
     * @param array<mixed> $filters
     */
    public function itemsPerPage(array $filters): ?int
    {
        if (! isset($filters[$this->itemsPerPageParameterName])) {
            return null;
        }

        return (int) $filters[$this->itemsPerPageParameterName];
    }
}
