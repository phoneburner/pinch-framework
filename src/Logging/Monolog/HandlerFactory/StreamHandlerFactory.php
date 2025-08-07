<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use PhoneBurner\Pinch\Filesystem\FileMode;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Exception\InvalidHandlerConfiguration;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class StreamHandlerFactory implements MonologHandlerFactory
{
    public function __construct(private readonly MonologFormatterFactory $formatters)
    {
    }

    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        \assert($config->handler_class === StreamHandler::class);
        return new StreamHandler(
            $config->handler_options['stream'] ?? throw new InvalidHandlerConfiguration('Missing Stream Handler Stream/Path'),
            Level::from($config->level->toMonlogLogLevel()),
            $config->bubble,
            $config->handler_options['file_permission'] ?? null,
            $config->handler_options['use_locking'] ?? false,
            $config->handler_options['file_open_mode'] ?? FileMode::WriteCreateOrAppendExisting->value,
        )->setFormatter($this->formatters->make($config));
    }
}
