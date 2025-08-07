<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * An adapter that collects data about all cache calls.
 */
class TraceableAdapter implements AdapterInterface, CacheInterface, PruneableInterface, ResettableInterface
{
    private array $calls = [];

    public function __construct(protected CacheItemPoolInterface $pool)
    {
    }

    #[\Override]
    public function get(string $key, callable $callback, float|null $beta = null, array|null &$metadata = null): mixed
    {
        if (! $this->pool instanceof CacheInterface) {
            throw new \BadMethodCallException(\sprintf('Cannot call "%s::get()": this class doesn\'t implement "%s".', \get_debug_type($this->pool), CacheInterface::class));
        }

        $event = $this->start(__FUNCTION__);

        $is_hit = true;
        try {
            $value = $this->pool->get($key, static function (ItemInterface $item, bool &$save) use ($callback, &$is_hit) {
                $is_hit = $item->isHit();
                return $callback($item, $save);
            }, $beta, $metadata);
            $event->result[$key] = \get_debug_type($value);
        } finally {
            $event->end = \microtime(true);
        }

        if ($is_hit) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }

        return $value;
    }

    #[\Override]
    public function getItem(mixed $key): CacheItem
    {
        $event = $this->start(__FUNCTION__);

        try {
            $item = $this->pool->getItem($key);
            \assert($item instanceof CacheItem);
        } finally {
            $event->end = \microtime(true);
        }

        $event->result[$key] = $item->isHit();
        if ($event->result[$key]) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }

        return $item;
    }

    #[\Override]
    public function hasItem(mixed $key): bool
    {
        $event = $this->start(__FUNCTION__);

        try {
            return $event->result[$key] = $this->pool->hasItem($key);
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function deleteItem(mixed $key): bool
    {
        $event = $this->start(__FUNCTION__);

        try {
            return $event->result[$key] = $this->pool->deleteItem($key);
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function save(CacheItemInterface $item): bool
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->getKey()] = $this->pool->save($item);
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $event = $this->start(__FUNCTION__);

        try {
            return $event->result[$item->getKey()] = $this->pool->saveDeferred($item);
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function getItems(array $keys = []): iterable
    {
        $event = $this->start(__FUNCTION__);

        try {
            $result = [...$this->pool->getItems($keys)];
        } finally {
            $event->end = \microtime(true);
        }

        $f = static function () use ($result, $event) {
            $event->result = [];
            foreach ($result as $key => $item) {
                $event->result[$key] = $item->isHit();
                if ($event->result[$key]) {
                    ++$event->hits;
                } else {
                    ++$event->misses;
                }
                yield $key => $item;
            }
        };

        return $f();
    }

    #[\Override]
    public function clear(string $prefix = ''): bool
    {
        $event = $this->start(__FUNCTION__);

        try {
            return $event->result['result'] = $this->pool instanceof AdapterInterface
                ? $this->pool->clear($prefix)
                : $this->pool->clear();
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function deleteItems(array $keys): bool
    {
        $event = $this->start(__FUNCTION__);

        $event->result['keys'] = $keys;
        try {
            return $event->result['result'] = $this->pool->deleteItems($keys);
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function commit(): bool
    {
        $event = $this->start(__FUNCTION__);

        try {
            return $event->result['result'] = $this->pool->commit();
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function prune(): bool
    {
        if (! $this->pool instanceof PruneableInterface) {
            return false;
        }

        $event = $this->start(__FUNCTION__);
        try {
            return $event->result['result'] = $this->pool->prune();
        } finally {
            $event->end = \microtime(true);
        }
    }

    #[\Override]
    public function reset(): void
    {
        if ($this->pool instanceof ResetInterface) {
            $this->pool->reset();
        }

        $this->clearCalls();
    }

    #[\Override]
    public function delete(string $key): bool
    {
        $event = $this->start(__FUNCTION__);

        try {
            return $event->result[$key] = $this->pool->deleteItem($key);
        } finally {
            $event->end = \microtime(true);
        }
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function clearCalls(): void
    {
        $this->calls = [];
    }

    public function getPool(): CacheItemPoolInterface
    {
        return $this->pool;
    }

    protected function start(string $name): TraceableAdapterEvent
    {
        return $this->calls[] = new TraceableAdapterEvent(
            $name,
            \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 15),
            \microtime(true),
        );
    }
}
