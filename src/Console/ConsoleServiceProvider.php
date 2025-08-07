<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console;

use Crell\AttributeUtils\ClassAnalyzer;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Framework\App\Command\DebugAppKeysCommand;
use PhoneBurner\Pinch\Framework\Console\Command\InteractivePinchShellCommand;
use PhoneBurner\Pinch\Framework\Console\Config\ShellConfigStruct;
use PhoneBurner\Pinch\Framework\Console\EventListener\ConsoleErrorListener;
use PhoneBurner\Pinch\Framework\EventDispatcher\Command\DebugEventListenersCommand;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CacheRoutesCommand;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\ListRoutesCommand;
use PhoneBurner\Pinch\Framework\Scheduler\Command\ConsumeScheduledMessagesCommand;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Scheduler\Command\DebugCommand as ScheduleDebugCommand;

use function PhoneBurner\Pinch\Type\narrow;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class ConsoleServiceProvider implements DeferrableServiceProvider
{
    public const array FRAMEWORK_COMMANDS = [
        InteractivePinchShellCommand::class,
        ListRoutesCommand::class,
        CacheRoutesCommand::class,
        DebugAppKeysCommand::class,
        DebugEventListenersCommand::class,
        ConsumeMessagesCommand::class,
        ConsumeScheduledMessagesCommand::class,
        StatsCommand::class,
        StopWorkersCommand::class,
        ScheduleDebugCommand::class,
        MailerTestCommand::class,
    ];

    public static function provides(): array
    {
        return [
            CliKernel::class,
            CommandLoaderInterface::class,
            Application::class,
            ConsoleApplication::class,
            InteractivePinchShellCommand::class,
            ConsoleErrorListener::class,
        ];
    }

    public static function bind(): array
    {
        return [
            Application::class => ConsoleApplication::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(
            CliKernel::class,
            static fn(App $app): CliKernel => new CliKernel(
                $app->get(ConsoleApplication::class),
                $app->get(EventDispatcherInterface::class),
            ),
        );

        $app->set(
            CommandLoaderInterface::class,
            static fn(App $app): CommandLoader => new CommandLoader($app->services, $app->get(ClassAnalyzer::class), [
                ...self::FRAMEWORK_COMMANDS,
                ...($app->config->get('console.commands') ?? []),
            ]),
        );

        $app->set(
            ConsoleErrorListener::class,
            static fn(App $app): ConsoleErrorListener => new ConsoleErrorListener($app->get(LoggerInterface::class)),
        );

        $app->set(ConsoleApplication::class, new ConsoleApplicationServiceFactory());

        $app->set(
            InteractivePinchShellCommand::class,
            static fn(App $app): InteractivePinchShellCommand => new InteractivePinchShellCommand(
                narrow(ShellConfigStruct::class, $app->config->get('console.shell')),
                $app->get(ContainerInterface::class),
            ),
        );
    }
}
