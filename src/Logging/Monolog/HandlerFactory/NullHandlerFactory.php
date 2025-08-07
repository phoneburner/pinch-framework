<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Level;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class NullHandlerFactory implements MonologHandlerFactory
{
    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        \assert($config->handler_class === NullHandler::class);
        return new NullHandler(Level::from($config->level->toMonlogLogLevel()));
    }
}
