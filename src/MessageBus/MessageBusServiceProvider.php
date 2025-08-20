<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory\NewInstanceServiceFactory;
use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Component\Logging\LogLevel;
use PhoneBurner\Pinch\Component\Logging\PsrLoggerAdapter;
use PhoneBurner\Pinch\Component\MessageBus\Handler\InvokableMessageHandler;
use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use PhoneBurner\Pinch\Container\ObjectContainer\ImmutableObjectContainer;
use PhoneBurner\Pinch\Framework\Database\Doctrine\ConnectionProvider;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\MessageBus\Config\BusConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\Config\MessageBusConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\Container\MessageBusContainer;
use PhoneBurner\Pinch\Framework\MessageBus\Container\ReceiverContainer;
use PhoneBurner\Pinch\Framework\MessageBus\Container\SenderContainer;
use PhoneBurner\Pinch\Framework\MessageBus\EventListener\LogWorkerMessageFailedEvent;
use PhoneBurner\Pinch\Framework\MessageBus\EventListener\ResetServicesListener;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\AmazonSqsTransportFactory;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\AmqpTransportFactory;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\ContainerTransportFactory;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\DoctrineTransportFactory;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\InMemoryTransportFactory;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\RedisTransportFactory;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory\SyncTransportFactory;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnCustomStopExceptionListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Handler\RedispatchMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Process\Messenger\RunProcessMessageHandler;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\proxy;
use function PhoneBurner\Pinch\Type\narrow;
use function PhoneBurner\Pinch\Type\narrow_callable;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class MessageBusServiceProvider implements ServiceProvider
{
    public static function bind(): array
    {
        return [
            MessageBusInterface::class => SymfonyMessageBusAdapter::class,
            MessageBus::class => SymfonyMessageBusAdapter::class,
            RoutableMessageBus::class => SymfonyRoutableMessageBusAdapter::class,
            TransportFactory::class => ContainerTransportFactory::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(DispatchAfterCurrentBusMiddleware::class, NewInstanceServiceFactory::singleton());
        $app->set(RunProcessMessageHandler::class, NewInstanceServiceFactory::singleton());
        $app->set(AmqpTransportFactory::class, NewInstanceServiceFactory::singleton());
        $app->set(AmazonSqsTransportFactory::class, NewInstanceServiceFactory::singleton());
        $app->set(LongRunningProcessServiceResetter::class, NewInstanceServiceFactory::singleton());
        $app->set(StopWorkerOnCustomStopExceptionListener::class, NewInstanceServiceFactory::singleton());
        $app->set(AddErrorDetailsStampListener::class, NewInstanceServiceFactory::singleton());
        $app->set(DispatchPcntlSignalListener::class, NewInstanceServiceFactory::singleton());

        $app->ghost(MessageBusContainer::class, static fn(MessageBusContainer $ghost): null => $ghost->__construct(
            \array_map(
                static fn (BusConfigStruct $bus): SymfonyMessageBusAdapter => ghost(
                    static fn(SymfonyMessageBusAdapter $ghost): null => $ghost->__construct(
                        \array_map(
                            static fn(string $class): MiddlewareInterface => narrow(MiddlewareInterface::class, $app->get($class)),
                            $bus->middleware,
                        ),
                    ),
                ),
                $app->get(MessageBusConfigStruct::class)->bus,
            ),
        ));

        $app->set(
            SymfonyMessageBusAdapter::class,
            proxy(static function (SymfonyMessageBusAdapter $proxy) use ($app): SymfonyMessageBusAdapter {
                return $app->get(MessageBusContainer::class)->default();
            }),
        );

        $app->ghost(SymfonyRoutableMessageBusAdapter::class, static fn(SymfonyRoutableMessageBusAdapter $ghost): null => $ghost->__construct(
            $app->get(MessageBusContainer::class),
            $app->get(MessageBusContainer::class)->default(),
        ));

        $app->ghost(ContainerTransportFactory::class, static fn(ContainerTransportFactory $ghost): null => $ghost->__construct(
            $app->services,
            $app->get(MessageBusConfigStruct::class)->transport_factories,
        ));

        $app->ghost(RedisTransportFactory::class, static fn(RedisTransportFactory $ghost): null => $ghost->__construct(
            $app->get(RedisManager::class),
            $app->environment,
        ));

        $app->ghost(DoctrineTransportFactory::class, static fn(DoctrineTransportFactory $ghost): null => $ghost->__construct(
            $app->get(ConnectionProvider::class),
            new PhpSerializer(),
        ));

        $app->ghost(SyncTransportFactory::class, static fn(SyncTransportFactory $ghost): null => $ghost->__construct(
            $app->get(MessageBusContainer::class),
        ));

        $app->ghost(InMemoryTransportFactory::class, static fn(InMemoryTransportFactory $ghost): null => $ghost->__construct(
            $app->get(Clock::class),
        ));

        $app->ghost(SenderContainer::class, static fn(SenderContainer $ghost): null => $ghost->__construct(\array_map(
            $app->get(TransportFactory::class)->make(...),
            $app->get(MessageBusConfigStruct::class)->senders,
        )));

        $app->ghost(ReceiverContainer::class, static fn(ReceiverContainer $ghost): null => $ghost->__construct(\array_map(
            $app->get(TransportFactory::class)->make(...),
            $app->get(MessageBusConfigStruct::class)->receivers,
        )));

        $app->ghost(SendMessageMiddleware::class, static fn(SendMessageMiddleware $ghost): null => $ghost->__construct(
            new SendersLocator(
                $app->get(MessageBusConfigStruct::class)->routing,
                $app->services->get(SenderContainer::class),
            ),
            $app->get(EventDispatcherInterface::class),
        ));

        $app->ghost(HandleMessageMiddleware::class, static fn(HandleMessageMiddleware $ghost): null => $ghost->__construct(
            new HandlersLocator(\array_map(
                static fn(array $handler_classes): array => \array_map(
                    static fn(string $class): callable => narrow_callable($app->get($class)),
                    $handler_classes,
                ),
                $app->get(MessageBusConfigStruct::class)->handlers,
            )),
        ));

        $app->ghost(DebugCommand::class, static fn(DebugCommand $ghost): null => $ghost->__construct(
            $app->get(MessageBusConfigStruct::class)->handlers,
        ));

        $app->ghost(StatsCommand::class, static fn(StatsCommand $ghost): null => $ghost->__construct(
            $app->get(ReceiverContainer::class),
            $app->get(ReceiverContainer::class)->keys(),
        ));

        $app->ghost(ConsumeMessagesCommand::class, fn(ConsumeMessagesCommand $ghost): null => $ghost->__construct(
            $app->get(RoutableMessageBus::class),
            $app->get(ReceiverContainer::class),
            $app->get(SymfonyEventDispatcherInterface::class),
            new PsrLoggerAdapter($app->get(LoggerInterface::class), LogLevel::Debug),
            $app->get(ReceiverContainer::class)->keys(),
            new ResetServicesListener(
                $app->get(LongRunningProcessServiceResetter::class),
                $app->get(LoggerInterface::class),
            ),
            $app->get(MessageBusContainer::class)->keys(),
        ));

        $app->ghost(StopWorkersCommand::class, static fn(StopWorkersCommand $ghost): null => $ghost->__construct(
            $app->get(CacheItemPoolInterface::class),
        ));

        $app->ghost(SendFailedMessageForRetryListener::class, static fn (SendFailedMessageForRetryListener $ghost): null => $ghost->__construct(
            $app->get(SenderContainer::class),
            new ImmutableObjectContainer(\array_map(
                static function (array $strategy): RetryStrategyInterface {
                    if ($strategy['class'] === MultiplierRetryStrategy::class) {
                        return new MultiplierRetryStrategy(
                            $strategy['params']['max_retries'] ?? 3,
                            $strategy['params']['delay'] ?? 1000,
                            $strategy['params']['multiplier'] ?? 1,
                            $strategy['params']['max_delay_ms'] ?? 0,
                            $strategy['params']['jitter'] ?? 0.1,
                        );
                    }

                    throw new \InvalidArgumentException(
                        \sprintf('Retry Strategy "%s" Not Currently Supported', $strategy['class']),
                    );
                },
                $app->get(MessageBusConfigStruct::class)->retry_strategy,
            )),
            $app->get(LoggerInterface::class),
            $app->get(EventDispatcherInterface::class),
        ));

        $app->ghost(SendFailedMessageToFailureTransportListener::class, static fn (SendFailedMessageToFailureTransportListener $ghost): null => $ghost->__construct(
            new SenderContainer(\array_map(
                $app->services->get(TransportFactory::class)->make(...),
                $app->get(MessageBusConfigStruct::class)->failure_senders,
            )),
            $app->get(LoggerInterface::class),
        ));

        $app->ghost(StopWorkerOnRestartSignalListener::class, static fn (StopWorkerOnRestartSignalListener $ghost): null => $ghost->__construct(
            $app->get(CacheItemPoolInterface::class),
            new PsrLoggerAdapter($app->get(LoggerInterface::class), LogLevel::Debug),
        ));

        $app->ghost(LogWorkerMessageFailedEvent::class, static fn (LogWorkerMessageFailedEvent $ghost): null => $ghost->__construct(
            $app->get(LoggerInterface::class),
        ));

        $app->ghost(InvokableMessageHandler::class, static fn (InvokableMessageHandler $ghost): null => $ghost->__construct(
            $app,
            $app->get(EventDispatcherInterface::class),
        ));

        $app->ghost(RedispatchMessageHandler::class, static fn (RedispatchMessageHandler $ghost): null => $ghost->__construct(
            $app->get(MessageBusInterface::class),
        ));

        $app->ghost(RunCommandMessageHandler::class, static fn (RunCommandMessageHandler $ghost): null => $ghost->__construct(
            $app->get(Application::class),
        ));
    }
}
