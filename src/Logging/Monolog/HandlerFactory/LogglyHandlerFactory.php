<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Formatter\LogglyFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\LogglyHandler;
use Monolog\Level;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Exception\InvalidHandlerConfiguration;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Handler\ResettableLogglyHandler;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class LogglyHandlerFactory implements MonologHandlerFactory
{
    public const string DEFAULT_FORMATTER = LogglyFormatter::class;

    public function __construct(private readonly MonologFormatterFactory $formatters)
    {
    }

    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        return (match ($config->handler_class) {
            ResettableLogglyHandler::class => new ResettableLogglyHandler(
                $config->handler_options['token'] ?? throw new InvalidHandlerConfiguration('Missing Loggly API Token'),
                Level::from($config->level->toMonlogLogLevel()),
                $config->bubble,
            ),
            LogglyHandler::class => new LogglyHandler(
                $config->handler_options['token'] ?? throw new InvalidHandlerConfiguration('Missing Loggly API Token'),
                Level::from($config->level->toMonlogLogLevel()),
                $config->bubble,
            ),
            default => throw new InvalidHandlerConfiguration('Invalid Handler Class for Factory'),
        })->setFormatter($this->formatters->make($config));
    }
}
