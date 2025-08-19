<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Environment;

use function PhoneBurner\Pinch\Type\cast_nullable_string;

class EnvironmentProcessor implements ProcessorInterface
{
    private string|null $git_commit = null;

    public function __construct(private readonly Environment $environment)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['context'] ??= $this->environment->context->name;

        $stage = $this->environment->stage;
        $record->extra['build_stage'] ??= $stage->value;

        if ($stage !== BuildStage::Development) {
            $record->extra['git_commit'] = $this->git_commit ??= $this->commit();
        }

        return $record;
    }

    private function commit(): string
    {
        $commit = cast_nullable_string($this->environment->env('PINCH_GIT_COMMIT'));
        return $commit ? \substr($commit, 0, 7) : 'unknown';
    }
}
