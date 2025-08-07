<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging;

use Monolog\Processor\PsrLogMessageProcessor;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory\DeferredServiceFactory;
use PhoneBurner\Pinch\Component\App\ServiceFactory\NewInstanceServiceFactory;
use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory\ContainerFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory\JsonFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory\LineFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory\LogglyFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\ContainerHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\ErrorLogHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\LogglyHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\NoopHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\NullHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\RotatingFileHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\SlackWebhookHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\StreamHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory\TestHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\LoggerExceptionHandler;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologLoggerServiceFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Processor\EnvironmentProcessor;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Processor\LogTraceProcessor;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Processor\PhoneNumberProcessor;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Processor\PsrMessageInterfaceProcessor;
use Psr\Log\LoggerInterface;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class LoggingServiceProvider implements ServiceProvider
{
    public static function bind(): array
    {
        return [
            LoggerServiceFactory::class => MonologLoggerServiceFactory::class,
            MonologFormatterFactory::class => ContainerFormatterFactory::class,
            MonologHandlerFactory::class => ContainerHandlerFactory::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(LoggerInterface::class, new DeferredServiceFactory(LoggerServiceFactory::class));

        $app->set(
            MonologLoggerServiceFactory::class,
            static fn(App $app): MonologLoggerServiceFactory => new MonologLoggerServiceFactory(
                $app->get(MonologHandlerFactory::class),
            ),
        );

        $app->set(PsrLogMessageProcessor::class, NewInstanceServiceFactory::singleton());

        $app->set(PhoneNumberProcessor::class, NewInstanceServiceFactory::singleton());

        $app->set(PsrMessageInterfaceProcessor::class, NewInstanceServiceFactory::singleton());

        $app->set(
            EnvironmentProcessor::class,
            static fn(App $app): EnvironmentProcessor => new EnvironmentProcessor(
                $app->environment,
            ),
        );

        $app->set(
            LogTraceProcessor::class,
            static fn(App $app): LogTraceProcessor => new LogTraceProcessor(
                $app->get(LogTrace::class),
            ),
        );

        $app->set(
            ContainerFormatterFactory::class,
            static fn(App $app): ContainerFormatterFactory => new ContainerFormatterFactory(
                $app->services,
                $app->get(LoggingConfigStruct::class)->formatter_factories,
            ),
        );

        $app->set(JsonFormatterFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(LineFormatterFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(LogglyFormatterFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(
            ContainerHandlerFactory::class,
            static fn(App $app): ContainerHandlerFactory => new ContainerHandlerFactory(
                $app->services,
                $app->get(LoggingConfigStruct::class)->handler_factories,
            ),
        );

        $app->set(
            ErrorLogHandlerFactory::class,
            static fn(App $app): ErrorLogHandlerFactory => new ErrorLogHandlerFactory(
                $app->get(MonologFormatterFactory::class),
            ),
        );

        $app->set(
            LogglyHandlerFactory::class,
            static fn(App $app): LogglyHandlerFactory => new LogglyHandlerFactory(
                $app->get(MonologFormatterFactory::class),
            ),
        );

        $app->set(
            RotatingFileHandlerFactory::class,
            static fn(App $app): RotatingFileHandlerFactory => new RotatingFileHandlerFactory(
                $app->get(MonologFormatterFactory::class),
            ),
        );

        $app->set(
            SlackWebhookHandlerFactory::class,
            static fn(App $app): SlackWebhookHandlerFactory => new SlackWebhookHandlerFactory(
                $app->get(MonologFormatterFactory::class),
            ),
        );

        $app->set(
            StreamHandlerFactory::class,
            static fn(App $app): StreamHandlerFactory => new StreamHandlerFactory(
                $app->get(MonologFormatterFactory::class),
            ),
        );

        $app->set(TestHandlerFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(NoopHandlerFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(NullHandlerFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(
            LoggerExceptionHandler::class,
            static fn(App $app): LoggerExceptionHandler => new LoggerExceptionHandler(
                $app->get(BuildStage::class),
            ),
        );

        $app->set(
            MonologLoggerServiceFactory::class,
            static fn(App $app): MonologLoggerServiceFactory => new MonologLoggerServiceFactory(
                $app->get(MonologHandlerFactory::class),
            ),
        );
    }
}
