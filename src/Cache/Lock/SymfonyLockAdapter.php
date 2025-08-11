<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache\Lock;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Cache\Lock\Lock;
use PhoneBurner\Pinch\Component\Cache\Lock\SharedLockMode;
use PhoneBurner\Pinch\Time\Interval\TimeInterval;
use PhoneBurner\Pinch\Time\Timer\StopWatch;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\SharedLockInterface;

#[Internal]
class SymfonyLockAdapter implements Lock, LoggerAwareInterface, SharedLockInterface
{
    public function __construct(private readonly SharedLockInterface $lock)
    {
    }

    #[\Override]
    public function acquire(
        bool $blocking = false,
        int $timeout_seconds = 300,
        int $delay_microseconds = 250_000,
        SharedLockMode $mode = SharedLockMode::Write,
    ): bool {
        $timer = StopWatch::start();
        do {
            $acquired = match ($mode) {
                SharedLockMode::Write => $this->lock->acquire(false),
                SharedLockMode::Read => $this->lock->acquireRead(false),
            };

            if ($acquired || $blocking === false) {
                return $acquired;
            }

            \usleep($delay_microseconds);
        } while ($timer->elapsed()->inSeconds() < $timeout_seconds);

        return false;
    }

    public function acquireRead(
        bool $blocking = false,
        int $timeout_seconds = 300,
        int $delay_microseconds = 250_000,
    ): bool {
        return $this->acquire(
            $blocking,
            $timeout_seconds,
            $delay_microseconds,
            SharedLockMode::Read,
        );
    }

    #[\Override]
    public function release(): void
    {
        $this->lock->release();
    }

    #[\Override]
    public function refresh(TimeInterval|float|null $ttl = null): void
    {
        $this->lock->refresh($ttl instanceof TimeInterval ? $ttl->seconds : $ttl);
    }

    #[\Override]
    public function acquired(): bool
    {
        return $this->lock->isAcquired();
    }

    #[\Override]
    public function ttl(): TimeInterval|null
    {
        $ttl = $this->lock->getRemainingLifetime();
        return $ttl !== null ? new TimeInterval(seconds: $ttl) : null;
    }

    #[\Override]
    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->lock instanceof LoggerAwareInterface) {
            $this->lock->setLogger($logger);
        }
    }

    public function wrapped(): SharedLockInterface
    {
        return $this->lock;
    }

    public function isAcquired(): bool
    {
        return $this->lock->isAcquired();
    }

    public function isExpired(): bool
    {
        return $this->lock->isExpired();
    }

    public function getRemainingLifetime(): float|null
    {
        return $this->lock->getRemainingLifetime();
    }
}
