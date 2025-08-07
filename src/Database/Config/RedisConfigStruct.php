<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;

final readonly class RedisConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param array<string, RedisConnectionConfigStruct> $connections
     */
    public function __construct(
        public array $connections = [],
        public int $timeout = 5,
        public string $default_connection = RedisManager::DEFAULT,
    ) {
        \assert($timeout > 0);
        \assert($default_connection !== '');
        \assert($connections === [] || \array_key_exists($default_connection, $connections));
    }
}
