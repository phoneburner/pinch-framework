<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Config;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Component\Logging\LogLevel;

final readonly class LoggingHandlerConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param class-string<HandlerInterface> $handler_class
     * @param class-string<FormatterInterface>|null $formatter_class
     */
    public function __construct(
        public string $handler_class,
        public array $handler_options = [],
        public string|null $formatter_class = null,
        public array $formatter_options = [],
        public LogLevel $level = LogLevel::Debug,
        public bool $bubble = true,
    ) {
    }
}
