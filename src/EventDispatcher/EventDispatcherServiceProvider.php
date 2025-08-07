<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventDispatcher;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Framework\EventDispatcher\Command\DebugEventListenersCommand;
use PhoneBurner\Pinch\Framework\EventDispatcher\EventListener\LogEventWasDispatched;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyContractsEventDispatcherInterface;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class EventDispatcherServiceProvider implements ServiceProvider
{
    public static function bind(): array
    {
        return [
            EventDispatcherInterface::class => SymfonyEventDispatcherAdapter::class,
            SymfonyEventDispatcherInterface::class => SymfonyEventDispatcherAdapter::class,
            SymfonyContractsEventDispatcherInterface::class => SymfonyEventDispatcherAdapter::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(SymfonyEventDispatcherAdapter::class, new EventDispatcherServiceFactory());
        $app->set(
            LogEventWasDispatched::class,
            static fn(App $app): LogEventWasDispatched => new LogEventWasDispatched(
                $app->get(LoggerInterface::class),
            ),
        );

        $app->set(
            DebugEventListenersCommand::class,
            static fn(App $app): DebugEventListenersCommand => new DebugEventListenersCommand(
                $app->get(SymfonyEventDispatcherInterface::class),
            ),
        );
    }
}
