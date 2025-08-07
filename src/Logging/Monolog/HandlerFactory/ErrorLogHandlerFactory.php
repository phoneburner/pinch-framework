<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class ErrorLogHandlerFactory implements MonologHandlerFactory
{
    public function __construct(private readonly MonologFormatterFactory $formatters)
    {
    }

    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        \assert($config->handler_class === ErrorLogHandler::class);

        return new ErrorLogHandler(
            $config->handler_options['message_type'] ?? ErrorLogHandler::OPERATING_SYSTEM,
            Level::from($config->level->toMonlogLogLevel()),
            $config->bubble,
            $config->handler_options['expand_newlines'] ?? false,
        )->setFormatter($this->formatters->make($config));
    }
}
