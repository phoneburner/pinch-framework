<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Config;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Processor\ProcessorInterface;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

final readonly class LoggingConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param string|null $channel Set the channel name to be used by the default
     * logger, this should normally be set to the application name in kabob-case,
     * which is the fallback if the channel is not set or null, identifying
     * the source of the log entry among other applications when aggregated in a
     * tool like Loggly.
     * @param array<class-string<ProcessorInterface>> $processors
     * @param array<LoggingHandlerConfigStruct> $handlers
     * @param array<class-string<HandlerInterface>, class-string<MonologHandlerFactory>> $handler_factories Custom handler
     * factories to use for a given handler class, in addition (or overriding) the
     * default factories.
     * @param array<class-string<FormatterInterface>, class-string<MonologFormatterFactory>> $formatter_factories Custom formatter
     * factories to use for a given formatter class, in addition (or overriding) the
     * default factories.
     */
    public function __construct(
        public string|null $channel,
        public array $processors,
        public array $handlers,
        public array $handler_factories = [],
        public array $formatter_factories = [],
    ) {
    }
}
