<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class AmpqConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param array<string, AmpqConnectionConfigStruct> $connections
     */
    public function __construct(
        public array $connections = [],
        public string $default_connection = 'default',
    ) {
        \assert($connections === [] || \array_key_exists($default_connection, $connections));
    }
}
