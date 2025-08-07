<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\ErrorHandling;

use PhoneBurner\Pinch\Exception\NotImplemented;

/**
 * Default error handler. If this class is bound to the interface in the container,
 * the existing error handler will not be overridden, and the default (PHP builtin)
 * behavior will be used.
 */
final readonly class NullErrorHandler implements ErrorHandler
{
    public function __invoke(int $level, string $message, string $file, int $line): bool
    {
        throw new NotImplemented('NullErrorHandler::__invoke() is not implemented');
    }
}
