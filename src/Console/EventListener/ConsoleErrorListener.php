<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\EventListener\ErrorListener as SymfonyConsoleErrorListener;

/**
 * Override the Symfony ErrorListener Subscriber to use the class names when
 * mapping the events to the listeners. This is necessary because the Symfony
 * class still uses non-PSR-14 compliant string aliases for the events.
 */
class ConsoleErrorListener extends SymfonyConsoleErrorListener
{
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleErrorEvent::class => ['onConsoleError', -128],
            ConsoleTerminateEvent::class => ['onConsoleTerminate', -128],
        ];
    }
}
