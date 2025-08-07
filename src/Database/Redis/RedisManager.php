<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Redis;

interface RedisManager
{
    public const string DEFAULT = 'default';

    public function connect(string $connection = self::DEFAULT): \Redis;
}
