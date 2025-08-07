<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Exception\InvalidConfiguration;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class RedisConnectionConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public string $host,
        public int $port = 6379,
        #[\SensitiveParameter] public string|null $password = null,
        public int $database = 0,
        public float $timeout = 0.0,
        public bool $persistent = true,
    ) {
        ($port > 0 && $port <= 65535) || throw new InvalidConfiguration('Redis Config Invalid: Port');
        $host !== '' || throw new InvalidConfiguration('Redis Config Invalid: Host');
        $password !== '' || throw new InvalidConfiguration('Redis Config Invalid: Password');
        ($database >= 0 && $database <= 15) || throw new InvalidConfiguration('Redis Config Invalid: Host');
    }
}
