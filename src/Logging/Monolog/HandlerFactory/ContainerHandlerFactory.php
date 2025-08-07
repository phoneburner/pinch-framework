<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\LogglyHandler;
use Monolog\Handler\NoopHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Handler\ResettableLogglyHandler;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;
use Psr\Container\ContainerInterface;

class ContainerHandlerFactory implements MonologHandlerFactory
{
    /**
     * @var array<class-string<HandlerInterface>, class-string<MonologHandlerFactory>>
     */
    public const array DEFAULT_FACTORIES = [
            ErrorLogHandler::class => ErrorLogHandlerFactory::class,
            LogglyHandler::class => LogglyHandlerFactory::class,
            ResettableLogglyHandler::class => LogglyHandlerFactory::class,
            NoopHandler::class => NoopHandlerFactory::class,
            NullHandler::class => NullHandlerFactory::class,
            RotatingFileHandler::class => RotatingFileHandlerFactory::class,
            SlackWebhookHandler::class => SlackWebhookHandlerFactory::class,
            StreamHandler::class => StreamHandlerFactory::class,
            TestHandler::class => TestHandlerFactory::class,
    ];

    /**
     * @var array<class-string<HandlerInterface>, class-string<MonologHandlerFactory>>
     */
    private readonly array $factories;

    /**
     * @param array<class-string<HandlerInterface>, class-string<MonologHandlerFactory>> $custom_factories
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $custom_factories = [],
    ) {
        $this->factories = \array_merge(self::DEFAULT_FACTORIES, $this->custom_factories);
    }

    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        $factory_class = $this->factories[$config->handler_class] ?? throw new \UnexpectedValueException(
            'Unsupported Handler Class: ' . $config->handler_class,
        );

        return $this->container->get($factory_class)->make($config);
    }
}
