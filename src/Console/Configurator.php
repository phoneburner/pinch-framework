<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console;

use Symfony\Component\Console\Command\Command;

interface Configurator
{
    public static function configure(Command $command): Command;
}
