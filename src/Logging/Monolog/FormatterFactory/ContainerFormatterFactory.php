<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\FormatterFactory;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use Psr\Container\ContainerInterface;

class ContainerFormatterFactory implements MonologFormatterFactory
{
    public const array DEFAULT_FACTORIES = [
        LogglyFormatter::class => LogglyFormatterFactory::class,
        LineFormatter::class => LineFormatterFactory::class,
        JsonFormatter::class => JsonFormatterFactory::class,
    ];

    /**
     * @var array<class-string<FormatterInterface>, class-string<MonologFormatterFactory>>
     */
    private readonly array $factories;

    /**
     * @param array<class-string<FormatterInterface>, class-string<MonologFormatterFactory>> $custom_factories
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $custom_factories = [],
    ) {
        $this->factories = \array_merge(self::DEFAULT_FACTORIES, $this->custom_factories);
    }

    public function make(LoggingHandlerConfigStruct $config): FormatterInterface
    {
        $formatter_class = $this->factories[$config->formatter_class ?? LineFormatter::class] ?? throw new \UnexpectedValueException(
            'Unsupported Formatter Class: ' . $config->formatter_class,
        );

        return $this->container->get($formatter_class)->make($config);
    }
}
