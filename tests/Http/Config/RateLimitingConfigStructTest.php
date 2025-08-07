<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Http\RateLimiter\NullRateLimiter;
use PhoneBurner\Pinch\Framework\Http\Config\RateLimitingConfigStruct;
use PhoneBurner\Pinch\Framework\Http\RateLimiter\RedisRateLimiter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimitingConfigStructTest extends TestCase
{
    #[Test]
    public function constructorSetsDefaults(): void
    {
        $config = new RateLimitingConfigStruct();

        self::assertTrue($config->enabled);
        self::assertSame(10, $config->default_per_second_max);
        self::assertSame(60, $config->default_per_minute_max);
        self::assertSame(NullRateLimiter::class, $config->rate_limiter_class);
        self::assertSame('rate_limit:', $config->redis_key_prefix);
    }

    #[Test]
    public function constructorAcceptsCustomValues(): void
    {
        $config = new RateLimitingConfigStruct(
            enabled: false,
            default_per_second_max: 5,
            default_per_minute_max: 100,
            rate_limiter_class: RedisRateLimiter::class,
            redis_key_prefix: 'custom:',
        );

        self::assertFalse($config->enabled);
        self::assertSame(5, $config->default_per_second_max);
        self::assertSame(100, $config->default_per_minute_max);
        self::assertSame(RedisRateLimiter::class, $config->rate_limiter_class);
        self::assertSame('custom:', $config->redis_key_prefix);
    }

    #[Test]
    public function isReadonly(): void
    {
        $config = new RateLimitingConfigStruct();

        // Verify that properties cannot be modified
        $reflection = new \ReflectionClass($config);
        $enabledProperty = $reflection->getProperty('enabled');

        self::assertTrue($enabledProperty->isReadOnly());
    }

    #[Test]
    public function implementsConfigStruct(): void
    {
        $config = new RateLimitingConfigStruct();

        self::assertInstanceOf(ConfigStruct::class, $config);
    }

    #[Test]
    public function supportsArrayAccess(): void
    {
        $config = new RateLimitingConfigStruct(
            enabled: false,
            default_per_second_max: 20,
        );

        // Test array access functionality from trait
        self::assertFalse($config['enabled']);
        self::assertSame(20, $config['default_per_second_max']);
        self::assertSame(60, $config['default_per_minute_max']); // default value
        self::assertArrayHasKey('enabled', $config);
        self::assertArrayNotHasKey('non_existent_key', $config);
    }

    #[Test]
    public function supportsSerialization(): void
    {
        $config = new RateLimitingConfigStruct(
            enabled: false,
            default_per_second_max: 5,
            default_per_minute_max: 100,
            rate_limiter_class: RedisRateLimiter::class,
            redis_key_prefix: 'test:',
        );

        $serialized = \serialize($config);
        $unserialized = \unserialize($serialized);

        self::assertInstanceOf(RateLimitingConfigStruct::class, $unserialized);
        self::assertFalse($unserialized->enabled);
        self::assertSame(5, $unserialized->default_per_second_max);
        self::assertSame(100, $unserialized->default_per_minute_max);
        self::assertSame(RedisRateLimiter::class, $unserialized->rate_limiter_class);
        self::assertSame('test:', $unserialized->redis_key_prefix);
    }
}
