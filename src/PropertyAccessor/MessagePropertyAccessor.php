<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyAccessor;

/** @method array<mixed> toArray() */
trait MessagePropertyAccessor
{
    public function __get(string $property): mixed
    {
        return $this->{$property};
    }
}
