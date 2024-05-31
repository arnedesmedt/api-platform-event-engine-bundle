<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Cache;

use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\ComplexTypeExtractor;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\CacheItem;

class CacheOrNullAdapter implements AdapterInterface
{
    private readonly NullAdapter $nullAdapter;

    public function __construct(
        private readonly AdapterInterface $adapter,
    ) {
        $this->nullAdapter = new NullAdapter();
    }

    private function adapter(): AdapterInterface
    {
        return ComplexTypeExtractor::complexTypeWanted()
            ? $this->nullAdapter
            : $this->adapter;
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->adapter()->getItem($key);
    }

    /** @inheritDoc */
    public function getItems(array $keys = []): iterable
    {
        return $this->adapter()->getItems($keys);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->adapter()->clear($prefix);
    }

    public function hasItem(string $key): bool
    {
        return $this->adapter()->hasItem($key);
    }

    public function deleteItem(string $key): bool
    {
        return $this->adapter()->deleteItem($key);
    }

    /** @inheritDoc */
    public function deleteItems(array $keys): bool
    {
        return $this->adapter()->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->adapter()->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->adapter()->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->adapter()->commit();
    }
}
