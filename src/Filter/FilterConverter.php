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

final class FilterConverter
{
    private string $pageParameterName;
    private string $orderParameterName;

    public function __construct(
        string $pageParameterName = 'page',
        string $orderParameterName = 'order'
    ) {
        $this->pageParameterName = $pageParameterName;
        $this->orderParameterName = $orderParameterName;
    }

    /**
     * @param array<mixed> $apiPlatformFilters
     */
    public function order(array $apiPlatformFilters): ?OrderBy
    {
        if (
            ! isset($apiPlatformFilters[$this->orderParameterName])
            || empty($apiPlatformFilters[$this->orderParameterName])
        ) {
            return null;
        }

        $orderProperties = $apiPlatformFilters[$this->orderParameterName];

        $orders = array_map(
            static function ($propertyName, $order) {
                $orderClass = sprintf('\EventEngine\DocumentStore\OrderBy\%s', ucfirst($order));

                return $orderClass::fromString($propertyName);
            },
            array_keys($orderProperties),
            $orderProperties,
        );

        $order = reset($orders);

        while (! empty($orders)) {
            $order = AndOrder::by($order, reset($orders));
        }

        return $order;
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
}
