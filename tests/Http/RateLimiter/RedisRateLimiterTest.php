<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\RateLimiter;

use DateTimeImmutable;
use PhoneBurner\Pinch\Component\Http\Domain\RateLimits;
use PhoneBurner\Pinch\Component\Http\Event\RequestRateLimitExceeded;
use PhoneBurner\Pinch\Component\Http\Event\RequestRateLimitUpdated;
use PhoneBurner\Pinch\Framework\Http\RateLimiter\RedisRateLimiter;
use PhoneBurner\Pinch\Time\Clock\StaticClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Redis;

final class RedisRateLimiterTest extends TestCase
{
    private Redis&MockObject $redis;
    private StaticClock $clock;
    private EventDispatcherInterface&MockObject $event_dispatcher;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->clock = new StaticClock(new DateTimeImmutable('@1642636800'));
        $this->event_dispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function createRateLimiter(string $key_prefix = 'rate_limit:', int $script_calls = 1): RedisRateLimiter
    {
        // Mock script loading
        $this->redis->expects($this->exactly($script_calls))
            ->method('script')
            ->with('LOAD', $this->isString())
            ->willReturn('test_script_sha');

        return new RedisRateLimiter($this->redis, $this->clock, $this->event_dispatcher, $key_prefix);
    }

    #[Test]
    public function throttleAllowsWhenWithinLimits(): void
    {
        $limits = new RateLimits(
            id: 'test-user',
            per_second: 10,
            per_minute: 60,
        );

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RequestRateLimitUpdated::class));

        // Mock Redis evalsha to return allowed result
        $this->redis->expects($this->once())
            ->method('evalsha')
            ->with(
                'test_script_sha',
                [
                    'rate_limit:test-user',
                    1642636800,
                    27377280,
                    10,
                    60,
                    1642636800,
                ],
                1,
            )
            ->willReturn([1, 9, 59]); // allowed, remaining_per_second, remaining_per_minute

        $rate_limiter = $this->createRateLimiter();
        $result = $rate_limiter->throttle($limits);

        self::assertTrue($result->allowed);
        self::assertSame(9, $result->remaining_per_second);
        self::assertSame(59, $result->remaining_per_minute);
        self::assertSame($limits, $result->rate_limits);
    }

    #[Test]
    public function throttleBlocksWhenLimitsExceeded(): void
    {
        $limits = new RateLimits(
            id: 'test-user',
            per_second: 1,
            per_minute: 5,
        );

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RequestRateLimitExceeded::class));

        // Mock Redis evalsha to return blocked result
        $this->redis->expects($this->once())
            ->method('evalsha')
            ->willReturn([0, 0, 0]); // blocked, no remaining

        $rate_limiter = $this->createRateLimiter();
        $result = $rate_limiter->throttle($limits);

        self::assertFalse($result->allowed);
        self::assertSame(0, $result->remaining_per_second);
        self::assertSame(0, $result->remaining_per_minute);
        self::assertSame($limits, $result->rate_limits);
    }

    #[Test]
    public function throttleUsesCustomKeyPrefix(): void
    {
        $limits = new RateLimits(id: 'user-123');

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RequestRateLimitUpdated::class));

        $this->redis->expects($this->once())
            ->method('evalsha')
            ->with(
                'test_script_sha',
                $this->callback(function ($args): bool {
                    return $args[0] === 'custom:user-123';
                }),
                1,
            )
            ->willReturn([1, 9, 59]);

        $rate_limiter = $this->createRateLimiter('custom:');
        $rate_limiter->throttle($limits);
    }

    #[Test]
    public function throttleHandlesRedisFailureGracefully(): void
    {
        $limits = new RateLimits(id: 'test-user');

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RequestRateLimitUpdated::class));

        // First evalsha call fails
        $this->redis->expects($this->exactly(2))
            ->method('evalsha')
            ->willThrowException(new \RedisException('Connection failed'));

        $rate_limiter = $this->createRateLimiter('rate_limit:', 2);
        $result = $rate_limiter->throttle($limits);

        // Should fallback to allowing with reduced limits
        self::assertTrue($result->allowed);
        self::assertSame(9, $result->remaining_per_second);
        self::assertSame(59, $result->remaining_per_minute);
    }

    #[Test]
    public function throttleHandlesInvalidRedisResponse(): void
    {
        $limits = new RateLimits(id: 'test-user');

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RequestRateLimitUpdated::class));

        $this->redis->expects($this->once())
            ->method('evalsha')
            ->willReturn('invalid'); // Invalid response type

        $rate_limiter = $this->createRateLimiter();
        $result = $rate_limiter->throttle($limits);

        // Should fallback to allowing
        self::assertTrue($result->allowed);
    }

    #[Test]
    public function throttleSetsCorrectResetTime(): void
    {
        $limits = new RateLimits(id: 'test-user');

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RequestRateLimitUpdated::class));

        $this->redis->expects($this->once())
            ->method('evalsha')
            ->willReturn([1, 9, 59]);

        $rate_limiter = $this->createRateLimiter();
        $result = $rate_limiter->throttle($limits);

        $expected_reset = new DateTimeImmutable('@1642636860');
        self::assertEquals($expected_reset, $result->reset_time);
    }
}
