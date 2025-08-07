# Logging

The logger instantiation/configuration can be completely overridden by implmenting
the `LoggerServiceFactory` interface, and overriding the default service container
binding in an application service provider.

### Default Logger Creation with `MonologLoggerServiceFactory`

- The factory returns a `PsrLoggerAdapter`, which wraps the actual `Monolog\Logger`
    - The Monolog `Logger` instance is configured inside a `ghost` closure (likely a framework-specific pattern for deferred execution or dependency resolution).
    - It retrieves the logging configuration from and casts it to . `$app->config->get('logging')``LoggingConfigStruct`
    - It determines the channel name from or defaults to a kebab-case version of the application name (). `logging.channel``app.name`
    - It fetches logging processors defined in from the service container. `logging.processors`
    - It sets a custom . `LoggerExceptionHandler`
    - It registers the logger instance back into the service container (), replacing any previous (potentially buffered) logger. `$app->services->setLogger($logger)`
    - It registers the logger with a , suggesting support for flushing logs in long-running processes like queue workers. `LongRunningProcessServiceResetter`

### Exception Handling

By default, the underlying Monolog instance is configured with the
`LoggerExceptionHandler` class, which suppresses all `\Throwable` instances thrown
while recording log messages when in Production. This is done to prevent the
application from crashing when a log message cannot be recorded. In the Development
and Integration build stages, the `\Throwable` is rethrown. The instance of the
`LoggerExceptionHandler` is resolved from the container, so this behavior can be
changed by extending the class and overriding in a service provider.

The downside of this is that if recording a log message fails, the error is not
bubbled up to the application, and the error might go unnoticed. To mitigate this,
logger handlers should be configured to fail gracefully, and fall back to another
handler if possible, so that the `LoggerExceptionHandler` is really just a last
resort to prevent the application from crashing due to logging errors.

    <?php // config/logging.php

    use Monolog\Handler;
    use Monolog\Formatter;
    use PhoneBurner\Pinch\Framework\Logging\LogLevel;

    return [
        'channel' => env('LOG_CHANNEL', 'pinch-app'),

        // Default handler used if a primary handler fails
        'fallback_handler' => [
            'handler_class' => Handler\ErrorLogHandler::class,
            'level' => LogLevel::Debug, // Using the framework's LogLevel enum/class
            'bubble' => true,
            // 'handler_options' => [...], // Options for ErrorLogHandler if needed
            // 'formatter_class' => Formatter\LineFormatter::class, // Optional formatter override
            // 'formatter_options' => [...], // Options for the formatter
        ],

        // Active log handlers
        'handlers' => [
            // Example: Rotating file handler
            'file' => [
                'handler_class' => Handler\RotatingFileHandler::class,
                'level' => LogLevel::Info,
                'bubble' => true, // Whether messages handled here should bubble up to other handlers
                'handler_options' => [
                    'filename' => storage_path('logs/app.log'),
                    'max_files' => 7,
                    // other RotatingFileHandler specific options...
                ],
                // Optional: Override default formatter for this handler type
                'formatter_class' => Formatter\LineFormatter::class,
                'formatter_options' => [
                    'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    'include_stacktraces' => true,
                ],
            ],

            // Example: Slack handler
            'slack' => [
                'handler_class' => Handler\SlackWebhookHandler::class,
                'level' => LogLevel::Critical,
                'bubble' => true,
                'handler_options' => [
                    'webhook_url' => env('LOG_SLACK_WEBHOOK_URL'),
                    'channel' => '#alerts',
                    // other SlackWebhookHandler specific options...
                ],
                'formatter_class' => Formatter\LineFormatter::class, // Can use JsonFormatter etc.
                'formatter_options' => [
                    'include_context_and_extra' => true,
                ]
            ],

            // Example: Null handler (discards logs)
            'null' => [
                 'handler_class' => Handler\NullHandler::class,
                 'level' => LogLevel::Debug, // Effectively disables logs below this level if bubble=false
                 'bubble' => false,
            ],
        ],

        // Processors applied to all log records for this channel
        'processors' => [
             // Service IDs of processor services/callables
             // \App\Logging\MyCustomProcessor::class,
             // \Monolog\Processor\MemoryUsageProcessor::class, // If registered as a service
        ],
    ];
