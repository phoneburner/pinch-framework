<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class ConsoleConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public array $commands = [],
        public ShellConfigStruct $shell = new ShellConfigStruct(),
    ) {
    }
}
