<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Scheduler;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\Configuration\Exception\InvalidConfiguration;
use PhoneBurner\Pinch\Container\ObjectContainer\MutableObjectContainer;
use PhoneBurner\Pinch\Framework\Scheduler\Command\ConsumeScheduledMessagesCommand;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Scheduler\Command\DebugCommand;
use Symfony\Component\Scheduler\EventListener\DispatchSchedulerEventListener;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;

use function PhoneBurner\Pinch\Type\narrow_class_string;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class SchedulerServiceProvider implements DeferrableServiceProvider
{
    public static function provides(): array
    {
        return [
            ScheduleProviderCollection::class,
            DebugCommand::class,
            DispatchSchedulerEventListener::class,
            ConsumeScheduledMessagesCommand::class,
        ];
    }

    public static function bind(): array
    {
        return [];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(ScheduleProviderCollection::class, static function (App $app): ScheduleProviderCollection {
            $schedule_providers = [];
            foreach ($app->config->get('scheduler.schedule_providers') ?: [] as $schedule_provider_class) {
                \assert(narrow_class_string(ScheduleProvider::class, $schedule_provider_class));
                $name = $schedule_provider_class::getName();
                if (\array_key_exists($name, $schedule_providers)) {
                    throw new InvalidConfiguration('Duplicate schedule provider name: ' . $name);
                }

                $schedule_providers[$name] = $app->get($schedule_provider_class);
            }

            return new ScheduleProviderCollection($schedule_providers);
        });

        $app->set(
            DebugCommand::class,
            static fn(App $app): DebugCommand => new DebugCommand(
                $app->get(ScheduleProviderCollection::class),
            ),
        );

        $app->set(
            ConsumeScheduledMessagesCommand::class,
            static function (App $app): ConsumeScheduledMessagesCommand {
                $clock = $app->get(ClockInterface::class);

                // Add a transport instance for every configured schedule provider
                /** @var MutableObjectContainer<SchedulerTransport> $receiver_locator */
                $receiver_locator = new MutableObjectContainer();
                foreach ($app->get(ScheduleProviderCollection::class) as $schedule_provider) {
                    \assert($schedule_provider instanceof ScheduleProvider);

                    $receiver_locator->set('schedule_' . $schedule_provider::getName(), new SchedulerTransport(
                        new MessageGenerator($schedule_provider, $schedule_provider::getName(), $clock),
                    ));
                }

                return new ConsumeScheduledMessagesCommand(
                    $app->get(RoutableMessageBus::class),
                    $receiver_locator,
                    $app->get(SymfonyEventDispatcherInterface::class),
                    $app->get(LoggerInterface::class),
                    $receiver_locator->keys(),
                );
            },
        );

        $app->set(
            DispatchSchedulerEventListener::class,
            static fn(App $app): DispatchSchedulerEventListener => new DispatchSchedulerEventListener(
                $app->get(ScheduleProviderCollection::class),
                $app->get(EventDispatcherInterface::class),
            ),
        );
    }
}
