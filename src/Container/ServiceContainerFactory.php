<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Container;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App as AppContract;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\App\ServiceContainer;
use PhoneBurner\Pinch\Component\App\ServiceContainer\ServiceContainerAdapter;
use PhoneBurner\Pinch\Component\App\ServiceContainerFactory as ServiceContainerFactoryContract;
use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Component\Configuration\Configuration;
use PhoneBurner\Pinch\Framework\App\App;
use PhoneBurner\Pinch\Framework\App\AppServiceProvider;
use PhoneBurner\Pinch\Framework\Cache\CacheServiceProvider;
use PhoneBurner\Pinch\Framework\Configuration\Environment;
use PhoneBurner\Pinch\Framework\Console\ConsoleServiceProvider;
use PhoneBurner\Pinch\Framework\Database\DatabaseServiceProvider;
use PhoneBurner\Pinch\Framework\EventDispatcher\EventDispatcherServiceProvider;
use PhoneBurner\Pinch\Framework\HealthCheck\HealthCheckServiceProvider;
use PhoneBurner\Pinch\Framework\Http\HttpServiceProvider;
use PhoneBurner\Pinch\Framework\HttpClient\HttpClientServiceProvider;
use PhoneBurner\Pinch\Framework\Logging\LoggingServiceProvider;
use PhoneBurner\Pinch\Framework\Mailer\MailerServiceProvider;
use PhoneBurner\Pinch\Framework\MessageBus\MessageBusServiceProvider;
use PhoneBurner\Pinch\Framework\Notifier\NotifierServiceProvider;
use PhoneBurner\Pinch\Framework\Scheduler\SchedulerServiceProvider;
use PhoneBurner\Pinch\Framework\Storage\StorageServiceProvider;

use function PhoneBurner\Pinch\ghost;

#[Internal]
class ServiceContainerFactory implements ServiceContainerFactoryContract
{
    /**
     * @var array<class-string<ServiceProvider>>
     */
    public const array FRAMEWORK_PROVIDERS = [
        AppServiceProvider::class,
        CacheServiceProvider::class,
        ConsoleServiceProvider::class,
        DatabaseServiceProvider::class,
        EventDispatcherServiceProvider::class,
        HealthCheckServiceProvider::class,
        HttpServiceProvider::class,
        HttpClientServiceProvider::class,
        LoggingServiceProvider::class,
        MailerServiceProvider::class,
        MessageBusServiceProvider::class,
        NotifierServiceProvider::class,
        SchedulerServiceProvider::class,
        StorageServiceProvider::class,
    ];

    public function make(AppContract $app): ServiceContainer
    {
        return ghost(static function (ServiceContainerAdapter $ghost) use ($app): void {
            $ghost->__construct($app);

            // Register the service providers in the order they are defined in the
            // framework an application, binding, deferring, and registering services.
            $deferral_enabled = (bool)$app->config->get('container.enable_deferred_service_registration');
            foreach ([...self::FRAMEWORK_PROVIDERS, ...$app->config->get('container.service_providers') ?: []] as $provider) {
                match (true) {
                    $deferral_enabled && self::deferrable($provider) => $ghost->defer($provider),
                    default => $ghost->register($provider),
                };
            }

            // Register the App, Configuration, and Environment instances after the
            // service providers have been registered to ensure that they are not
            // accidentally overridden by a service provider definition.
            $ghost->set(Configuration::class, $app->config);
            $ghost->set(Environment::class, $app->environment);
            $ghost->set(ServiceContainer::class, $ghost);
            $ghost->set(AppContract::class, $app);
            $ghost->set(App::class, $app);
        });
    }

    /**
     * @phpstan-assert-if-true class-string<DeferrableServiceProvider>|DeferrableServiceProvider $provider
     */
    private static function deferrable(object|string $provider): bool
    {
        return \is_a($provider, DeferrableServiceProvider::class, true);
    }
}
