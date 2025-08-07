<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\HandlerFactory;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Level;
use PhoneBurner\Pinch\Framework\Logging\Config\LoggingHandlerConfigStruct;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Exception\InvalidHandlerConfiguration;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologFormatterFactory;
use PhoneBurner\Pinch\Framework\Logging\Monolog\MonologHandlerFactory;

class SlackWebhookHandlerFactory implements MonologHandlerFactory
{
    public function __construct(private readonly MonologFormatterFactory $formatters)
    {
    }

    public function make(LoggingHandlerConfigStruct $config): HandlerInterface
    {
        \assert($config->handler_class === SlackWebhookHandler::class);
        return new SlackWebhookHandler(
            $config->handler_options['webhook_url'] ?? throw new InvalidHandlerConfiguration('Missing Slack Webhook URL'),
            $config->handler_options['channel'] ?? null,
            $config->handler_options['username'] ?? null,
            $config->handler_options['use_attachment'] ?? true,
            $config->handler_options['icon_emoji'] ?? null,
            $config->handler_options['use_short_attachment'] ?? false,
            $config->handler_options['include_context_and_extra'] ?? false,
            Level::from($config->level->toMonlogLogLevel()),
            $config->bubble,
            $config->handler_options['exclude_fields'] ?? [],
        )->setFormatter($this->formatters->make($config));
    }
}
