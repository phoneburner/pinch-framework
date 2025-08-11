<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Cache\Lock;

use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyLockAdapter;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyLockFactoryAdapter;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyNamedKey;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyNamedKeyFactory;
use PhoneBurner\Pinch\Time\Interval\TimeInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory as SymfonyLockFactory;
use Symfony\Component\Lock\SharedLockInterface;

final class SymfonyLockFactoryAdapterTest extends TestCase
{
    #[Test]
    public function lockFactorySetsLoggerOnWrappedFactory(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $symfony_lock_factory = $this->createMock(SymfonyLockFactory::class);
        $symfony_lock_factory->expects($this->once())->method('setLogger')->with($logger);

        $lock_factory = new SymfonyLockFactoryAdapter(new SymfonyNamedKeyFactory(), $symfony_lock_factory);

        $lock_factory->setLogger($logger);
    }

    #[DataProvider('providesTests')]
    #[Test]
    public function lockFactoryCreatesLocks(
        Key|\Stringable|string $key,
        bool $auto_release,
        int|float $ttl,
    ): void {
        $ttl = new TimeInterval(seconds: $ttl);
        $symfony_lock_factory = $this->createMock(SymfonyLockFactory::class);
        $symfony_lock_factory->expects($this->once())
            ->method('createLockFromKey')
            ->with(new Key('locks.test_resource_key'), $ttl->seconds, $auto_release)
            ->willReturn($this->createMock(SharedLockInterface::class));

        $lock_factory = new SymfonyLockFactoryAdapter(new SymfonyNamedKeyFactory(), $symfony_lock_factory);

        $lock = $lock_factory->make($key, $ttl, $auto_release);

        self::assertInstanceOf(SymfonyLockAdapter::class, $lock);
    }

    public static function providesTests(): \Generator
    {
        $keys = [
            'TestResourceKey',
            new SymfonyNamedKey('test_resource_key'),
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'TestResourceKey';
                }
            },
        ];

        foreach ($keys as $key) {
            foreach ([true, false] as $auto_release) {
                foreach ([0, 1, 250, \PHP_INT_MAX] as $ttl) {
                    yield [$key, $auto_release, $ttl];
                }
            }
        }
    }
}
