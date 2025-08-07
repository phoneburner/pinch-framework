<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Framework\Console\Command\InteractivePinchShellCommand;

final readonly class ShellConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param array<string, class-string> $services Map of variable names to service class-strings to inject into the shell
     * @param list<class-string> $imports Application Imports to inject into the shell
     * @param array<string,mixed> $options Configuration Options for PsySH
     * @link https://github.com/bobthecow/psysh/wiki/Config-options
     */
    public function __construct(
        public array $services = [],
        public array $imports = [],
        public array $options = InteractivePinchShellCommand::DEFAULT_PSYSH_OPTIONS,
    ) {
    }
}
