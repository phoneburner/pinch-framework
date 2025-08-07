<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NoopHandler;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class NoopHandlerFactory implements MonologHandlerFactory
{
    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        \assert($config->handler_class === NoopHandler::class);
        return new NoopHandler();
    }
}
