<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;

class LineFormatterFactory implements MonologFormatterFactory
{
    public function make(LoggingHandlerConfigStruct $config): FormatterInterface
    {
        return new LineFormatter(
            $config->formatter_options['format'] ?? null,
            $config->formatter_options['date_format'] ?? null,
            $config->formatter_options['allow_inline_line_breaks'] ?? false,
            $config->formatter_options['ignore_empty_context_and_extra'] ?? false,
            $config->formatter_options['include_stacktraces'] ?? false,
        );
    }
}
