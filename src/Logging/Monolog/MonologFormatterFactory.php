<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog;

use Monolog\Formatter\FormatterInterface;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;

interface MonologFormatterFactory
{
    public function make(LoggingHandlerConfigStruct $config): FormatterInterface;
}
