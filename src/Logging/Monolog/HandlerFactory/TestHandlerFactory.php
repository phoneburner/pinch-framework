<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory\ContainerFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class TestHandlerFactory implements MonologHandlerFactory
{
    public function __construct(private readonly ContainerFormatterFactory $formatters)
    {
    }

    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        \assert($config->handler_class === TestHandler::class);
        return new TestHandler(
            Level::from($config->level->toMonlogLogLevel()),
            $config->bubble,
        )->setFormatter($this->formatters->make($config));
    }
}
