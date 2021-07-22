<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ADS\Util\StringUtil;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use EventEngine\DocumentStore\Filter\AndFilter;
use EventEngine\DocumentStore\Filter\EqFilter;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\Filter\LikeFilter;
use EventEngine\DocumentStore\Filter\OrFilter;
use EventEngine\DocumentStore\OrderBy\AndOrder;
use EventEngine\DocumentStore\OrderBy\OrderBy;

use function array_filter;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_pop;
use function count;
use function reset;
use function sprintf;
use function ucfirst;

final class DocumentStoreFilterConverter extends FilterConverter
{
    /**
     * @inheritDoc
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
            static function (string $propertyName, $order) {
                $orderClass = sprintf('\EventEngine\DocumentStore\OrderBy\%s', ucfirst($order));

                return $orderClass::fromString(sprintf('state.%s', StringUtil::camelize($propertyName)));
            },
            array_keys($orderProperties),
            $orderProperties,
        );

        $order = array_pop($orders);

        while (! empty($orders)) {
            $order = AndOrder::by(array_pop($orders), $order);
        }

        return $order;
    }

    /**
     * @inheritDoc
     */
    public function filter(array $filters, string $resourceClass): ?Filter
    {
        $searchFilter = ($this->filterFinder)($resourceClass, SearchFilter::class);

        if ($searchFilter === null) {
            return null;
        }

        $descriptions = $searchFilter->getDescription($resourceClass);
        $filters = array_intersect_key($filters, $descriptions);

        $filters = array_map(
            fn ($decamilizedPropertyName, $filterValue) => $this->eventEngineSearchFilter(
                $descriptions[$decamilizedPropertyName],
                $filterValue
            ),
            array_keys($filters),
            $filters
        );

        $filters = array_filter($filters);

        if (empty($filters)) {
            return null;
        }

        if (count($filters) === 1) {
            return reset($filters);
        }

        return new AndFilter(...$filters);
    }

    /**
     * @param array<string, mixed> $description
     */
    private function eventEngineSearchFilter(array $description, string $value): ?Filter
    {
        $property = sprintf('state.%s', StringUtil::camelize($description['property']));
        switch ($description['strategy']) {
            case SearchFilterInterface::STRATEGY_EXACT:
                return new EqFilter($property, $value);

            case SearchFilterInterface::STRATEGY_PARTIAL:
                return new LikeFilter($property, '%' . $value . '%');

            case SearchFilterInterface::STRATEGY_START:
                return new LikeFilter($property, $value . '%');

            case SearchFilterInterface::STRATEGY_END:
                return new LikeFilter($property, '%' . $value);

            case SearchFilterInterface::STRATEGY_WORD_START:
                return new OrFilter(
                    new LikeFilter($property, $value . '%'),
                    new LikeFilter($property, '% ' . $value . '%'),
                );
        }

        return null;
    }
}
