<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventDispatcher;

use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory;
use PhoneBurner\Pinch\Framework\Console\EventListener\ConsoleErrorListener;
use PhoneBurner\Pinch\Framework\EventDispatcher\Config\EventDispatcherConfigStruct;
use PhoneBurner\Pinch\Framework\EventDispatcher\EventListener\LazyListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnCustomStopExceptionListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Scheduler\EventListener\DispatchSchedulerEventListener;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\Type\is_class_string_of;
use function PhoneBurner\Pinch\Type\narrow;

class EventDispatcherServiceFactory implements ServiceFactory
{
    private const array FRAMEWORK_SUBSCRIBERS = [
            // Console Subscribers
            ConsoleErrorListener::class,

            // Messenger Subscribers
            AddErrorDetailsStampListener::class,
            DispatchPcntlSignalListener::class,
            SendFailedMessageForRetryListener::class,
            SendFailedMessageToFailureTransportListener::class,
            StopWorkerOnCustomStopExceptionListener::class,
            StopWorkerOnRestartSignalListener::class,

            // Scheduler Subscribers
            DispatchSchedulerEventListener::class,
    ];

    private array $cache = [];

    public function __invoke(App $app, string $id): SymfonyEventDispatcherAdapter
    {
        $config = narrow(EventDispatcherConfigStruct::class, $app->config->get('event_dispatcher'));

        try {
            $event_dispatcher = ghost(function (EventDispatcher $ghost) use ($app, $config): void {
                $ghost->__construct();

                foreach (\array_unique([...self::FRAMEWORK_SUBSCRIBERS, ...$config->subscribers]) as $subscriber) {
                    \assert(is_class_string_of(EventSubscriberInterface::class, $subscriber));
                    foreach ($subscriber::getSubscribedEvents() as $event => $methods) {
                        $this->registerSubscriberListeners($app, $ghost, $event, $subscriber, $methods);
                    }
                }

                foreach ($config->listeners as $event => $listeners) {
                    foreach ($listeners as $listener) {
                        $ghost->addListener($event, $this->listener($app, $listener));
                    }
                }
            });

            return new SymfonyEventDispatcherAdapter(
                $event_dispatcher,
                $app->get(LoggerInterface::class),
                $config->event_dispatch_log_level,
                $config->event_failure_log_level,
            );
        } finally {
            // we only needed the listener cache while constructing the event dispatcher
            // so we should clear it now to free up memory
            $this->cache = [];
        }
    }

    private function listener(
        App $app,
        string $listener_class,
        string|null $listener_method = null,
    ): callable {
        return $this->cache[$listener_class . '.' . $listener_method] ??= self::resolve(
            $app,
            $listener_class,
            $listener_method,
        );
    }

    private static function resolve(
        App $app,
        string $listener_class,
        string|null $listener_method = null,
    ): callable {
        \assert(\class_exists($listener_class) || \interface_exists($listener_class));
        $reflection = new \ReflectionClass($listener_class);
        if (! $reflection->isInstantiable()) {
            return new LazyListener($app, $listener_class, $listener_method);
        }

        $proxy = $reflection->newLazyProxy(static fn(object $object): object => $reflection->initializeLazyObject(
            $app->get($object::class),
        ));

        if ($listener_method !== null) {
            return $proxy->$listener_method(...);
        }

        \assert(\is_callable($proxy));
        return $proxy;
    }

    private function registerSubscriberListeners(
        App $app,
        EventDispatcherInterface $dispatcher,
        string $event,
        string $subscriber,
        array|string $methods,
    ): void {
        match (true) {
            \is_string($methods) => $dispatcher->addListener($event, $this->listener(
                $app,
                $subscriber,
                $methods,
            )),
            \is_string($methods[0]) => $dispatcher->addListener($event, $this->listener(
                $app,
                $subscriber,
                $methods[0],
            ), $methods[1] ?? 0),
            default => \array_walk($methods, fn(array|string $methods): null => $this->registerSubscriberListeners(
                $app,
                $dispatcher,
                $event,
                $subscriber,
                $methods,
            )),
        };
    }
}
