<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class AmpqConnectionConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        #[\SensitiveParameter] public string $password,
    ) {
        \assert($port > 0 && $port <= 65535);
        \assert($host !== '');
        \assert($user !== '');
        \assert($password !== '');
    }
}
