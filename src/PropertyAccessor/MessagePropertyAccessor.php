<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyAccessor;

/**
 * @method array<mixed> toArray()
 */
trait MessagePropertyAccessor
{
    /**
     * @return mixed
     */
    public function __get(string $property)
    {
        return $this->{$property};
    }
}
