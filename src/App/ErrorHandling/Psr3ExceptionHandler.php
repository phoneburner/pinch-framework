<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\ErrorHandling;

use PhoneBurner\Pinch\Component\Logging\LogLevel;
use Psr\Log\LoggerInterface;

class Psr3ExceptionHandler implements ExceptionHandler
{
    /**
     * @param ExceptionHandler|bool $rethrow one of:
     * - true: rethrow the exception
     * - false: do nothing, continue execution
     * - ExceptionHandler: call this wrapped handler with the exception
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ExceptionHandler|bool $rethrow = true,
    ) {
    }

    public function __invoke(\Throwable $e): void
    {
        $log_level = $e instanceof \Error ? LogLevel::Critical : LogLevel::Error;
        $message = \sprintf("Uncaught Exception: %s in %s:%s", $e->getMessage(), $e->getFile(), $e->getLine());
        $this->logger->log($log_level->value, $message, [
            'exception' => $e,
        ]);

        match ($this->rethrow) {
            true => throw $e, // Rethrow the exception
            false => null, // Do nothing, continue execution
            default => ($this->rethrow)($e), // Call the wrapped handler if provided
        };
    }
}
