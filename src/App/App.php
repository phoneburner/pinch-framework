<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App;

use PhoneBurner\Pinch\Component\App\App as AppContract;
use PhoneBurner\Pinch\Component\App\Event\ApplicationBootstrap;
use PhoneBurner\Pinch\Component\App\Event\ApplicationTeardown;
use PhoneBurner\Pinch\Component\App\ServiceContainer;
use PhoneBurner\Pinch\Component\App\ServiceContainerFactory as ServiceContainerFactoryContract;
use PhoneBurner\Pinch\Component\App\ServiceFactory\GhostServiceFactory;
use PhoneBurner\Pinch\Component\App\ServiceFactory\ProxyServiceFactory;
use PhoneBurner\Pinch\Component\Configuration\Configuration;
use PhoneBurner\Pinch\Component\Configuration\ConfigurationFactory as ConfigurationFactoryContract;
use PhoneBurner\Pinch\Component\Configuration\Environment as EnvironmentContract;
use PhoneBurner\Pinch\Container\ParameterOverride\OverrideCollection;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\ErrorHandler;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\ExceptionHandler;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\NullErrorHandler;
use PhoneBurner\Pinch\Framework\App\ErrorHandling\NullExceptionHandler;
use PhoneBurner\Pinch\Framework\Configuration\ConfigurationFactory;
use PhoneBurner\Pinch\Framework\Container\ServiceContainerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * This is the main application class. It is a container that holds context,
 * environment state, configuration, and services. It should be the only singleton
 * service in the application, so that tearing it can result in complete garbage
 * collection and reduce the possibility of memory leaks or stale/shared state.
 *
 * While the class is a container, it is not intended to be used as a general-purpose
 * service container itself. The implemented container methods are really shortcuts to
 * the underlying service container.
 */
final class App implements AppContract
{
    public ServiceContainer $services;

    public Configuration $config;

    private static self|null $instance = null;

    public static function booted(): bool
    {
        return self::$instance !== null;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \RuntimeException('Application has not been bootstrapped.');
    }

    /**
     * @param null|callable(self):(mixed|void) $callback An optional callback
     * executed at the very start of the setup process, allowing you to
     * modify the application instance before it is fully set up, e.g., before
     * we start asking the container for services or dispatching events.
 */
    public static function bootstrap(
        EnvironmentContract $environment,
        ConfigurationFactoryContract|Configuration|null $config = null,
        ServiceContainerFactoryContract|ServiceContainer|null $services = null,
        callable|null $callback = null,
    ): self {
        self::booted() && throw new \RuntimeException('Application has already been bootstrapped.');
        self::$instance = new self($environment, $config, $services);
        return self::$instance->setup($callback);
    }

    /**
     * Handle any setup steps that require the application to be fully initialized,
     * e.g., anything that requires the configuration or services to be available,
     * or the path() or env() helper functions.
     *
     * @param null|callable(self):(mixed|void) $callback An optional callback
     * executed at the very start of the setup process, allowing you to
     * modify the application instance before it is fully set up, e.g., before
     * we start asking the container for services or dispatching events.
     */
    private function setup(callable|null $callback = null): self
    {
        if ($callback !== null) {
            $callback($this);
        }

        // set error handler
        $error_handler = $this->services->get(ErrorHandler::class);
        if (! $error_handler instanceof NullErrorHandler) {
            \set_error_handler($error_handler);
        }

        // set exception handler
        $exception_handler = $this->services->get(ExceptionHandler::class);
        if (! $exception_handler instanceof NullExceptionHandler) {
            \set_exception_handler($exception_handler);
        }

        // dispatch bootstrap event
        $this->services->get(EventDispatcherInterface::class)->dispatch(new ApplicationBootstrap($this));

        return $this;
    }

    /**
     * @param null|callable(self):(mixed|void) $callback
     */
    public static function teardown(callable|null $callback = null): null
    {
        self::$instance?->cleanup($callback);
        return self::$instance = null;
    }

    /**
     * This method is called when the application is being torn down, providing
     * a hook for any cleanup that needs to be done while we are guaranteed the
     * application is still in a valid state.
     *
     * @param null|callable(self):(mixed|void) $callback An optional callback
     * executed at the very end of the application lifecycle. This might be useful
     * for cleaning up resources that may require special handling or checking
     * the final application state during testing, but is mostly for symmetry with
     * the setup method.
     */
    private function cleanup(callable|null $callback = null): void
    {
        $this->services->get(EventDispatcherInterface::class)->dispatch(new ApplicationTeardown($this));
        if ($callback !== null) {
            $callback($this);
        }
    }

    /**
     * Wrap a callback in the context of an application lifecycle instance. Note
     * that if exit() is called within the callback, the application will still be
     * torn down properly because App::teardown(...) is registered as a shutdown
     * function.
     *
     * @template TReturn
     * @param callable(AppContract): TReturn $callback
     * @return TReturn
     */
    public static function exec(EnvironmentContract $environment, callable $callback): mixed
    {
        try {
            return $callback(self::bootstrap($environment));
        } finally {
            self::teardown();
        }
    }

    /**
     * Note: to avoid nasty chicken-and-egg race conditions, especially as both
     * the configuration and container are dependent on the instance of the App,
     * both factories must return lazy ghost instances, even though the instances
     * will be instantiated almost immediately. For example, configuration files
     * may use functions like path() or env() which may be dependent on the App instance.
     */
    private function __construct(
        public readonly EnvironmentContract $environment,
        ConfigurationFactoryContract|Configuration|null $config = null,
        ServiceContainerFactoryContract|ServiceContainer|null $services = null,
    ) {
        $this->config = match (true) {
            $config === null => new ConfigurationFactory()->make($environment),
            $config instanceof ConfigurationFactoryContract => $config->make($environment),
            default => $config,
        };

        $this->services = match (true) {
            $services === null => new ServiceContainerFactory()->make($this),
            $services instanceof ServiceContainerFactoryContract => $services->make($this),
            default => $services,
        };
    }

    public function has(\Stringable|string $id, bool $strict = false): bool
    {
        return $this->services->has($id);
    }

    /**
     * @template T of object
     * @return ($id is class-string<T> ? T : never)
     * @phpstan-assert class-string<T> $id
     */
    public function get(\Stringable|string $id): object
    {
        $value = $this->services->get($id);
        /** @var T $value */
        return $value;
    }

    public function set(\Stringable|string $id, mixed $value): void
    {
        $this->services->set($id, $value);
    }

    public function unset(\Stringable|string $id): void
    {
        $this->services->unset($id);
    }

    public function call(
        object|string $object,
        string $method = '__invoke',
        OverrideCollection|null $overrides = null,
    ): mixed {
        return $this->services->call($object, $method, $overrides);
    }

    /**
     * @template T of object
     * @param class-string<T> $id,
     * @param \Closure(T): void|\Closure(T): null $initializer
     */
    public function ghost(string $id, \Closure $initializer): void
    {
        $this->services->set($id, new GhostServiceFactory($id, $initializer));
    }

    /**
     * @template T of object
     * @param class-string<T> $id,
     * @param \Closure(T): T $factory
     */
    public function proxy(string $id, \Closure $factory): void
    {
        $this->services->set($id, new ProxyServiceFactory($id, $factory));
    }
}
