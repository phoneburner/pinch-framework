<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\LogglyHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\Logging\PsrLoggerAdapter;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\LoggerServiceFactory;
use PhoneBurner\Pinch\Framework\MessageBus\LongRunningProcessServiceResetter;
use PhoneBurner\Pinch\String\StringCase;
use Psr\Log\LoggerInterface;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\Type\narrow;

class MonologLoggerServiceFactory implements LoggerServiceFactory
{
    public const array DEFAULT_FORMATTERS = [
        LogglyHandler::class => LogglyFormatter::class,
        RotatingFileHandler::class => LineFormatter::class,
        StreamHandler::class => LineFormatter::class,
        SlackWebhookHandler::class => LineFormatter::class,
        ErrorLogHandler::class => LineFormatter::class,
        TestHandler::class => LineFormatter::class,
    ];

    public function __construct(
        private readonly MonologHandlerFactory $handler_factory,
    ) {
    }

    public function __invoke(App $app, string $id): LoggerInterface
    {
        return new PsrLoggerAdapter(ghost(function (Logger $logger) use ($app): void {
            $config = narrow(LoggingConfigStruct::class, $app->config->get('logging'));

            $logger->__construct(
                $config->channel ?? StringCase::Kabob->from($app->config->get('app.name')),
                \array_map($this->handler_factory->make(...), \array_values($config->handlers)),
                \array_map($app->services->get(...), $config->processors),
            );

            // Set a custom exception handler to suppress any errors that occur
            // while processing a log entry in production environments.
            $logger->setExceptionHandler($app->get(LoggerExceptionHandler::class)(...));

            // On resolution, replace the resolved logger as the container's
            // logger instance, which should also consume any buffered log
            // entries from the default buffer logger. It's ok that we're setting
            // the inner logger here, because the PsrLoggerAdapter is a wrapper around
            // the Monolog logger, and we don't need to worry about it being
            // replaced again.
            $app->services->setLogger($logger);

            // Register with the long-running process service resetter to make sure
            // that we batch/flush any buffered log entries when the worker stops.
            $app->services->get(LongRunningProcessServiceResetter::class)->add($logger);
        }));
    }
}
