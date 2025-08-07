<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\ErrorHandling;

use PhoneBurner\Pinch\Exception\NotImplemented;

/**
 * Default exception handler. If this class is bound to the interface in the container,
 * the existing exception handler will not be overridden, and the default (PHP builtin)
 * behavior will be used.
 */
final readonly class NullExceptionHandler implements ExceptionHandler
{
    public function __invoke(\Throwable $e): void
    {
        throw new NotImplemented('NullErrorHandler::__invoke() is not implemented', previous: $e);
    }
}
