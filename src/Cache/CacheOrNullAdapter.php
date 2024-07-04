<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Cache;

use ADS\Bundle\EventEngineBundle\Type\ComplexTypeExtractor;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CacheOrNullAdapter implements AdapterInterface, CacheInterface, PruneableInterface, ResettableInterface
{
    private readonly NullAdapter $nullAdapter;

    public function __construct(
        private readonly AdapterInterface&CacheInterface $adapter,
    ) {
        $this->nullAdapter = new NullAdapter();
    }

    private function adapter(): AdapterInterface&CacheInterface
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

    /** @param array<mixed>|null $metadata */
    public function get(string $key, callable $callback, float|null $beta = null, array|null &$metadata = null): mixed
    {
        return $this->adapter()->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->adapter()->delete($key);
    }

    public function prune(): bool
    {
        $adapter = $this->adapter();

        if ($adapter instanceof PruneableInterface) {
            return $adapter->prune();
        }

        return true;
    }

    public function reset(): void
    {
        $adapter = $this->adapter();

        if (! ($adapter instanceof ResettableInterface)) {
            return;
        }

        $adapter->reset();
    }
}
