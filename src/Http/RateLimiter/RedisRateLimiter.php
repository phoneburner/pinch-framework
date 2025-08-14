<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RateLimiter;

use DateTimeImmutable;
use PhoneBurner\Pinch\Attribute\Usage\Experimental;
use PhoneBurner\Pinch\Component\Http\Domain\RateLimits;
use PhoneBurner\Pinch\Component\Http\Event\RequestRateLimitExceeded;
use PhoneBurner\Pinch\Component\Http\Event\RequestRateLimitUpdated;
use PhoneBurner\Pinch\Component\Http\RateLimiter\RateLimiter;
use PhoneBurner\Pinch\Component\Http\RateLimiter\RateLimitResult;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\EventDispatcher\EventDispatcherInterface;
use Redis;

/**
 * Redis-based rate limiter with sliding window algorithm
 *
 * Uses atomic Redis operations to track per-second and per-minute limits.
 * Implements sliding window algorithm with hash structure for efficiency.
 * Provides accurate rate limiting even under high concurrency.
 */
#[Experimental]
final readonly class RedisRateLimiter implements RateLimiter
{
    private readonly string $script_sha;

    public function __construct(
        private Redis $redis,
        private Clock $clock,
        private EventDispatcherInterface $event_dispatcher,
        private string $key_prefix = 'rate_limit:',
    ) {
        // Pre-load and cache the Lua script
        $this->script_sha = $this->loadScript();
    }

    public function throttle(RateLimits $limits): RateLimitResult
    {
        $now = $this->clock->now();
        $timestamp = $now->getTimestamp();
        $id = $limits->id;

        // Use hash structure: rate_limit:{id}
        $key = $this->key_prefix . $id;

        // Calculate current second and minute windows
        $current_second = $timestamp;
        $current_minute = (int)($timestamp / 60);

        try {
            // Use evalsha to execute cached script
            $result = $this->redis->evalsha($this->script_sha, [
                $key,
                $current_second,
                $current_minute,
                $limits->per_second,
                $limits->per_minute,
                $timestamp,
            ], 1);
        } catch (\RedisException) {
            // Try to reload and execute script directly if SHA is not cached
            $script_sha = $this->loadScript();
            try {
                $result = $this->redis->evalsha($script_sha, [
                    $key,
                    $current_second,
                    $current_minute,
                    $limits->per_second,
                    $limits->per_minute,
                    $timestamp,
                ], 1);
            } catch (\RedisException) {
                // Fallback to allow if script fails
                $result = RateLimitResult::allowed(
                    remaining_per_second: $limits->per_second - 1,
                    remaining_per_minute: $limits->per_minute - 1,
                    reset_time: $this->getResetTime($now),
                    rate_limits: $limits,
                );

                $this->event_dispatcher->dispatch(new RequestRateLimitUpdated($result));
                return $result;
            }
        }

        if (! \is_array($result) || \count($result) !== 3) {
            // Fallback to allow if result is malformed
            $fallback_result = RateLimitResult::allowed(
                remaining_per_second: $limits->per_second - 1,
                remaining_per_minute: $limits->per_minute - 1,
                reset_time: $this->getResetTime($now),
                rate_limits: $limits,
            );

            $this->event_dispatcher->dispatch(new RequestRateLimitUpdated($fallback_result));
            return $fallback_result;
        }

        $allowed = (bool)$result[0];
        $remaining_per_second = (int)$result[1];
        $remaining_per_minute = (int)$result[2];

        if ($allowed) {
            $allowed_result = RateLimitResult::allowed(
                remaining_per_second: $remaining_per_second,
                remaining_per_minute: $remaining_per_minute,
                reset_time: $this->getResetTime($now),
                rate_limits: $limits,
            );

            $this->event_dispatcher->dispatch(new RequestRateLimitUpdated($allowed_result));
            return $allowed_result;
        }

        $blocked_result = RateLimitResult::blocked(
            reset_time: $this->getResetTime($now),
            rate_limits: $limits,
        );

        $this->event_dispatcher->dispatch(new RequestRateLimitExceeded($blocked_result));
        return $blocked_result;
    }

    /**
     * Load and cache the Lua script in Redis
     */
    private function loadScript(): string
    {
        $script = <<<'LUA'
local key = KEYS[1]
local current_second = tonumber(ARGV[1])
local current_minute = tonumber(ARGV[2])
local per_second_limit = tonumber(ARGV[3])
local per_minute_limit = tonumber(ARGV[4])
local timestamp = tonumber(ARGV[5])

-- Hash fields for tracking
local second_field = "s:" .. current_second
local minute_field = "m:" .. current_minute

-- Get current counts
local per_second_count = tonumber(redis.call('HGET', key, second_field) or 0)
local per_minute_count = tonumber(redis.call('HGET', key, minute_field) or 0)

-- Check if limits would be exceeded
if per_second_count >= per_second_limit or per_minute_count >= per_minute_limit then
    -- Return blocked result: allowed, remaining_per_second, remaining_per_minute
    return {0, math.max(0, per_second_limit - per_second_count), math.max(0, per_minute_limit - per_minute_count)}
end

-- Increment counters
per_second_count = redis.call('HINCRBY', key, second_field, 1)
per_minute_count = redis.call('HINCRBY', key, minute_field, 1)

-- Set expiration for the hash (expire after 2 minutes to be safe)
redis.call('EXPIRE', key, 120)

-- Clean old entries to prevent unbounded growth
-- Remove second entries older than 2 seconds
local cleanup_second = current_second - 2
redis.call('HDEL', key, "s:" .. cleanup_second)

-- Remove minute entries older than 2 minutes
local cleanup_minute = current_minute - 2
redis.call('HDEL', key, "m:" .. cleanup_minute)

-- Return allowed result: allowed, remaining_per_second, remaining_per_minute
return {1, per_second_limit - per_second_count, per_minute_limit - per_minute_count}
LUA;

        return $this->redis->script('LOAD', $script);
    }

    /**
     * Calculate when the rate limit will reset (next minute boundary)
     */
    private function getResetTime(DateTimeImmutable $now): DateTimeImmutable
    {
        // Reset at the next minute boundary
        $next_minute = $now->setTime((int)$now->format('H'), (int)$now->format('i') + 1, 0);

        return $next_minute;
    }
}
