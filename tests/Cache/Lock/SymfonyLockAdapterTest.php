<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Cache\Lock;

use PhoneBurner\Pinch\Component\Cache\Lock\SharedLockMode;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyLockAdapter;
use PhoneBurner\Pinch\Time\TimeInterval\TimeInterval;
use PhoneBurner\Pinch\Time\Timer\StopWatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\SharedLockInterface;

final class SymfonyLockAdapterTest extends TestCase
{
    #[Test]
    public function adapterSetsLoggerOnWrappedLock(): void
    {
        $symfony_lock = new class implements SharedLockInterface, LoggerAwareInterface {
            use LoggerAwareTrait;

            public function acquire(bool $blocking = false): bool
            {
                return false;
            }

            public function acquireRead(bool $blocking = false): bool
            {
                return false;
            }

            public function release(): void
            {
            }

            public function refresh(float|null $ttl = null): void
            {
            }

            public function isAcquired(): bool
            {
                return false;
            }

            public function isExpired(): bool
            {
                return false;
            }

            public function getRemainingLifetime(): float|null
            {
                return null;
            }

            public function getLogger(): LoggerInterface
            {
                return $this->logger ?? throw new \RuntimeException('Logger not set');
            }
        };

        $logger = $this->createMock(LoggerInterface::class);

        $lock = new SymfonyLockAdapter($symfony_lock);
        $lock->setLogger($logger);

        self::assertSame($logger, $symfony_lock->getLogger());
    }

    #[DataProvider('providesBooleanValues')]
    #[Test]
    public function adapterAcquiresWriteLockWithoutBlocking(bool $acquired): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(false)->willReturn($acquired);

        $adapter = new SymfonyLockAdapter($lock);
        $timer = StopWatch::start();
        $adapter->acquire(blocking: false, mode: SharedLockMode::Write);
        self::assertLessThan(25000, $timer->elapsed()->inMicroseconds());
    }

    #[DataProvider('providesBooleanValues')]
    #[Test]
    public function adapterAcquiresReadLockWithoutBlocking(bool $acquired): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquireRead')->with(false)->willReturn($acquired);

        $adapter = new SymfonyLockAdapter($lock);
        $timer = StopWatch::start();
        $adapter->acquire(blocking: false, mode: SharedLockMode::Read);
        self::assertLessThan(25000, $timer->elapsed()->inMicroseconds());
    }

    #[Test]
    public function adapterAcquiresSuccessfulWriteLockWithBlocking(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(3))
            ->method('acquire')
            ->with(false)
            ->willReturn(false, false, true);

        $adapter = new SymfonyLockAdapter($lock);
        $timer = StopWatch::start();
        $adapter->acquire(blocking: true, timeout_seconds: 5, mode: SharedLockMode::Write);
        self::assertGreaterThan(25000, $timer->elapsed()->inMicroseconds());
    }

    #[Test]
    public function adapterAcquiresSuccessfulReadLockWithBlocking(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(3))
            ->method('acquireRead')
            ->with(false)
            ->willReturn(false, false, true);

        $adapter = new SymfonyLockAdapter($lock);
        $timer = StopWatch::start();
        $adapter->acquire(blocking: true, timeout_seconds: 5, mode: SharedLockMode::Read);
        self::assertGreaterThan(25000, $timer->elapsed()->inMicroseconds());
    }

    #[Test]
    public function adapterTimesOutOnWriteLockWithBlocking(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->atLeast(2))
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $adapter = new SymfonyLockAdapter($lock);
        $timer = StopWatch::start();
        $adapter->acquire(blocking: true, timeout_seconds: 1, mode: SharedLockMode::Write);
        self::assertGreaterThan(25000, $timer->elapsed()->inMicroseconds());
    }

    #[Test]
    public function adapterTimesOutOnReadLockWithBlocking(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->atLeast(2))
            ->method('acquireRead')
            ->with(false)
            ->willReturn(false);

        $adapter = new SymfonyLockAdapter($lock);
        $timer = StopWatch::start();
        $adapter->acquire(blocking: true, timeout_seconds: 1, mode: SharedLockMode::Read);
        self::assertGreaterThan(25000, $timer->elapsed()->inMicroseconds());
    }

    #[Test]
    public function adapterReleasesLock(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('release');

        $adapter = new SymfonyLockAdapter($lock);
        $adapter->release();
    }

    #[Test]
    public function adapterRefreshesLockWithTtl(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('refresh')->with(123);

        $adapter = new SymfonyLockAdapter($lock);
        $adapter->refresh(new TimeInterval(seconds: 123.45));
    }

    #[Test]
    public function adapterRefreshesLockWithoutTtl(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('refresh')->with(null);

        $adapter = new SymfonyLockAdapter($lock);
        $adapter->refresh(null);
    }

    #[DataProvider('providesBooleanValues')]
    #[Test]
    public function adapterChecksIfLockIsAcquired(bool $is_acquired): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('isAcquired')->willReturn($is_acquired);

        $adapter = new SymfonyLockAdapter($lock);
        self::assertSame($is_acquired, $adapter->acquired());
    }

    #[DataProvider('providesTtlValues')]
    #[Test]
    public function adapterReturnsNonNullTtl(int|float $ttl): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('getRemainingLifetime')->willReturn($ttl);

        $adapter = new SymfonyLockAdapter($lock);

        self::assertEquals(new TimeInterval(seconds: $ttl), $adapter->ttl());
    }

    #[Test]
    public function adapterReturnsNullTtl(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('getRemainingLifetime')->willReturn(null);

        $adapter = new SymfonyLockAdapter($lock);

        self::assertNull($adapter->ttl());
    }

    public static function providesBooleanValues(): \Generator
    {
        yield [true];
        yield [false];
    }

    public static function providesTtlValues(): \Generator
    {
        yield [300.0];
        yield [30.25];
        yield [0.0];
    }
}
