<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\ErrorHandling;

use PhoneBurner\Pinch\Component\Logging\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Note that some error levels cannot be handled by a user-defined error
 * handler, since they occur during PHP's initialization or are triggered
 * when the engine is in an unrecoverable state. As of PHP 8.4, these are:
 * - E_CORE_ERROR
 * - E_CORE_WARNING
 * - E_COMPILE_ERROR
 * - E_COMPILE_WARNING
 * - E_PARSE
 * - E_ERROR
 */
class Psr3ErrorHandler implements ErrorHandler
{
    private const array LEVEL_MAP = [
        \E_RECOVERABLE_ERROR => LogLevel::Critical,
        \E_USER_ERROR => LogLevel::Error,
        \E_WARNING => LogLevel::Warning,
        \E_USER_WARNING => LogLevel::Warning,
        \E_NOTICE => LogLevel::Notice,
        \E_USER_NOTICE => LogLevel::Notice,
        \E_DEPRECATED => LogLevel::Debug,
        \E_USER_DEPRECATED => LogLevel::Debug,
    ];

    private const array NAME_MAP = [
        \E_RECOVERABLE_ERROR => 'Recoverable Error',
        \E_USER_ERROR => 'User Error',
        \E_WARNING => 'Warning',
        \E_USER_WARNING => 'User Warning',
        \E_NOTICE => 'Notice',
        \E_USER_NOTICE => 'User Notice',
        \E_DEPRECATED => 'Deprecated',
        \E_USER_DEPRECATED => 'User Deprecated',
    ];

    /**
     * @param ErrorHandler|bool $return one of the following:
     *  - true: suppress the default error handler and continue execution
     *  - false: the default error handler is used will continue.
     *  - ErrorHandler: call and return the wrapped error handler's return value
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ErrorHandler|bool $return = false,
    ) {
    }

    public function __invoke(int $level, string $message, string $file, int $line): bool
    {
        $this->logger->log(
            (self::LEVEL_MAP[$level] ?? LogLevel::Error)->value,
            \sprintf('Unhandled %s: %s in %s:%s', self::NAME_MAP[$level] ?? 'Error', $message, $file, $line),
        );

        return \is_bool($this->return) ? $this->return : ($this->return)($level, $message, $file, $line);
    }
}
