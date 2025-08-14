<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\AttributeUtils\Psr6CacheAnalyzer;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\Exception\KernelError;
use PhoneBurner\Pinch\Component\App\Kernel;
use PhoneBurner\Pinch\Component\App\ServiceContainer;
use PhoneBurner\Pinch\Component\App\ServiceFactory\ConfigStructServiceFactory;
use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Component\AttributeAnalysis\AttributeAnalyzer;
use PhoneBurner\Pinch\Component\Cache\CacheDriver;
use PhoneBurner\Pinch\Component\Cache\Psr6\CacheItemPoolFactory;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Configuration;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Cryptography\Defaults;
use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\KeyChain;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Container\Exception\NotResolvable;
use PhoneBurner\Pinch\Container\InvokingContainer;
use PhoneBurner\Pinch\Container\MutableContainer;
use PhoneBurner\Pinch\Framework\App\App as FrameworkApp;
use PhoneBurner\Pinch\Framework\App\Config\AppConfigStruct;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\ErrorHandler;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\ExceptionHandler;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\NullErrorHandler;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\NullExceptionHandler;
use PhoneBurner\Pinch\Framework\Cache\Config\CacheConfigStruct;
use PhoneBurner\Pinch\Framework\Configuration\Environment;
use PhoneBurner\Pinch\Framework\Console\CliKernel;
use PhoneBurner\Pinch\Framework\Console\Config\ConsoleConfigStruct;
use PhoneBurner\Pinch\Framework\Container\Config\ContainerConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Config\DatabaseConfigStruct;
use PhoneBurner\Pinch\Framework\EventDispatcher\Config\EventDispatcherConfigStruct;
use PhoneBurner\Pinch\Framework\HealthCheck\Config\HealthCheckConfigStruct;
use PhoneBurner\Pinch\Framework\Http\Config\HttpConfigStruct;
use PhoneBurner\Pinch\Framework\Http\HttpKernel;
use PhoneBurner\Pinch\Framework\HttpClient\Config\HttpClientConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingConfigStruct;
use PhoneBurner\Pinch\Framework\Mailer\Config\MailerConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\Config\MessageBusConfigStruct;
use PhoneBurner\Pinch\Framework\Notifier\Config\NotifierConfigStruct;
use PhoneBurner\Pinch\Framework\Scheduler\Config\SchedulerConfigStruct;
use PhoneBurner\Pinch\Framework\Storage\Config\StorageConfigStruct;
use PhoneBurner\Pinch\Random\Randomizer;
use PhoneBurner\Pinch\Time\Clock\Clock;
use PhoneBurner\Pinch\Time\Clock\HighResolutionTimer;
use PhoneBurner\Pinch\Time\Clock\SystemClock;
use PhoneBurner\Pinch\Time\Clock\SystemHighResolutionTimer;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\Type\narrow;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class AppServiceProvider implements ServiceProvider
{
    public const array SERVICE_LEVEL_CONFIG_STRUCTS = [
        'app' => AppConfigStruct::class,
        'cache' => CacheConfigStruct::class,
        'console' => ConsoleConfigStruct::class,
        'container' => ContainerConfigStruct::class,
        'database' => DatabaseConfigStruct::class,
        'event_dispatcher' => EventDispatcherConfigStruct::class,
        'health_check' => HealthCheckConfigStruct::class,
        'http' => HttpConfigStruct::class,
        'http_client' => HttpClientConfigStruct::class,
        'logging' => LoggingConfigStruct::class,
        'mailer' => MailerConfigStruct::class,
        'message_bus' => MessageBusConfigStruct::class,
        'notifier' => NotifierConfigStruct::class,
        'scheduler' => SchedulerConfigStruct::class,
        'storage' => StorageConfigStruct::class,
    ];

    public static function bind(): array
    {
        return [
            ClockInterface::class => Clock::class,
            ClassAnalyzer::class => AttributeAnalyzer::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        // These services must be set explicitly in the container by the application
        // after service provider registration.
        $app->set(App::class, static fn (App $app): never => throw new NotResolvable(App::class));
        $app->set(FrameworkApp::class, static fn (App $app): never => throw new NotResolvable(FrameworkApp::class));
        $app->set(Environment::class, static fn (App $app): never => throw new NotResolvable(Environment::class));
        $app->set(Configuration::class, static fn (App $app): never => throw new NotResolvable(Configuration::class));
        $app->set(ServiceContainer::class, static fn (App $app): never => throw new NotResolvable(ServiceContainer::class));

        // When asked for a concrete instance or an implementation of a
        // container-like interface, the container should return itself, unless
        // specifically asking for the ServiceContainer. These are defined here,
        // and not in the bind method, since they already exist and lazy loading
        // would add unnecessary overhead.
        $app->set(ContainerInterface::class, $app);
        $app->set(InvokingContainer::class, $app);
        $app->set(MutableContainer::class, $app);

        // These are the few services that should always be eagerly instantiated
        // since they are used on every request and are less expensive to create
        // than to wrap with a closure to defer instantiation. They should also
        // be safe to instantiate this early in the application lifecycle, since
        // they are not dependent on configuration.
        $app->set(ErrorHandler::class, new NullErrorHandler());
        $app->set(ExceptionHandler::class, new NullExceptionHandler());
        $app->set(LogTrace::class, LogTrace::make());
        $app->set(BuildStage::class, $app->environment->stage);
        $app->set(Context::class, $app->environment->context);
        $app->set(Clock::class, new SystemClock());
        $app->set(HighResolutionTimer::class, new SystemHighResolutionTimer());
        $app->set(Randomizer::class, new Randomizer());

        // It is probably safe for us to resolve the service level config structs
        // eagerly; however, for the sake of being extra cautious, we will wrap the
        // services in a service factory.
        foreach (self::SERVICE_LEVEL_CONFIG_STRUCTS as $key => $config_struct) {
            $app->set($config_struct, new ConfigStructServiceFactory($key));
        }

        // Note: we use a regular closure here instead of binding the interface to
        // a concrete implementation because we may be in a context where there
        // is no kernel available (e.g. running tests), and this gives us a clean
        // way to fail in that case.
        $app->set(Kernel::class, static fn(App $app): Kernel => $app->get(match ($app->environment->context) {
            Context::Http => HttpKernel::class,
            Context::Cli => CliKernel::class,
            default => throw new KernelError('Context is Not Defined or Supported'),
        }));

        $app->set(
            KeyChain::class,
            ghost(static fn (KeyChain $ghost): null => $ghost->__construct(
                $app->get(AppConfigStruct::class)->key ?? throw new \LogicException(
                    'App Key Must Be Defined in Configuration',
                ),
            )),
        );

        $app->set(
            Natrium::class,
            ghost(static function (Natrium $ghost) use ($app): void {
                $config = narrow(AppConfigStruct::class, $app->get(AppConfigStruct::class));
                $ghost->__construct(
                    $app->get(KeyChain::class),
                    $app->get(Clock::class),
                    new Defaults(
                        $config->symmetric_algorithm,
                        $config->asymmetric_algorithm,
                    ),
                );
            }),
        );

        $app->set(
            AttributeAnalyzer::class,
            static fn(App $app): AttributeAnalyzer => new AttributeAnalyzer(ghost(static function (MemoryCacheAnalyzer $ghost) use ($app): void {
                $ghost->__construct(
                    new Psr6CacheAnalyzer(
                        new Analyzer(),
                        $app->get(CacheItemPoolFactory::class)->make(match ($app->environment->stage) {
                            BuildStage::Development => CacheDriver::Memory,
                            default => CacheDriver::File,
                        }),
                    ),
                );
            })),
        );
    }
}
