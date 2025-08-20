<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Configuration\Environment;

use function PhoneBurner\Pinch\Type\cast_nullable_string;

class EnvironmentProcessor implements ProcessorInterface
{
    private string|null $hostname = null;

    private string|null $git_commit = null;

    private string|null $ip_address = null;

    private array|null $request_info = null;

    public function __construct(private readonly Environment $environment)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['php'] ??= \PHP_VERSION;
        $record->extra['hostname'] ??= ($this->hostname ??= $this->environment->hostname());
        $record->extra['context'] ??= $this->environment->context->name;
        $record->extra['build_stage'] ??= $this->environment->stage->value;

        if ($this->environment->stage !== BuildStage::Development) {
            $record->extra['git_commit'] ??= ($this->git_commit ??= $this->commit());
        }

        if ($this->environment->context === Context::Http) {
            $record->extra['ip_address'] ??= ($this->ip_address ??= cast_nullable_string(
                $this->environment->server('HTTP_TRUE_CLIENT_IP')
                ?? $this->environment->server('HTTP_X_FORWARDED_FOR')
                ?? $this->environment->server('REMOTE_ADDR'),
            ));
            $record->extra['request'] ??= ($this->request_info ??= \array_filter([
                'method' => $this->environment->server('REQUEST_METHOD'),
                'host' => $this->environment->server('HTTP_X_FORWARDED_HOST') ?? $this->environment->server('HTTP_HOST'),
                'path' => $this->environment->server('REQUEST_URI'),
                'query' => $this->environment->server('QUERY_STRING'),
            ]));
        }

        return $record;
    }

    private function commit(): string
    {
        $commit = cast_nullable_string($this->environment->env('PINCH_GIT_COMMIT'));
        return $commit ? \substr($commit, 0, 7) : 'unknown';
    }
}
