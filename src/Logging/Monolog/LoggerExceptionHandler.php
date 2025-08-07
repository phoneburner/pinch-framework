<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog;

use Monolog\LogRecord;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;

/**
 * The exception handler for Throwable instances thrown within in the Monolog
 * logger while writing a log record. This class is intentionally not final to
 * allow for end-users to extend it and provide their own exception handling logic.
 */
class LoggerExceptionHandler
{
    public function __construct(private readonly BuildStage $build_stage)
    {
    }

    public function __invoke(\Throwable $e, LogRecord $record): void
    {
        // Only suppress errors in production environments
        if ($this->build_stage !== BuildStage::Production) {
            throw $e;
        }
    }
}
