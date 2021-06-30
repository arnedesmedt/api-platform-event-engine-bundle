<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

abstract class FilterConverter
{
    protected string $pageParameterName;
    protected string $itemsPerPageParameterName;
    protected string $orderParameterName;

    public function __construct(
        string $pageParameterName = 'page',
        string $itemsPerPageParameterName = 'items-per-page',
        string $orderParameterName = 'order'
    ) {
        $this->pageParameterName = $pageParameterName;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
        $this->orderParameterName = $orderParameterName;
    }
}
