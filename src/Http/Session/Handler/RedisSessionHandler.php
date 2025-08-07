<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Time\TimeInterval\TimeInterval;

#[Internal]
final class RedisSessionHandler extends SessionHandler
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly TimeInterval $ttl,
    ) {
    }

    public function read(SessionId|string $id): string
    {
        return (string)$this->redis->get(self::key($id));
    }

    public function write(SessionId|string $id, string $data): bool
    {
        return $this->redis->setex(self::key($id), $this->ttl->seconds, $data);
    }

    public function destroy(SessionId|string $id): bool
    {
        return (bool)$this->redis->del(self::key($id));
    }

    /**
     * Note: we cannot use PhoneBurner\Pinch\Component\Cache\CacheKey::make()
     * like we normally would for creating a normalized cache key because our session
     * key has invalid characters for a PSR-6, which get converted to underscores,
     * reducing the variance of the session id.
     */
    private static function key(SessionId|string $id): string
    {
        return 'session.' . $id;
    }
}
