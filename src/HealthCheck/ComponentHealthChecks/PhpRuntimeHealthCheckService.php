<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks;

use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentType;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Time\Clock\Clock;

class PhpRuntimeHealthCheckService implements ComponentHealthCheckService
{
    public const string COMPONENT_NAME = 'php';

    public function __construct(
        private readonly LogTrace $log_trace,
    ) {
    }

    #[\Override]
    public function __invoke(Clock $clock): array
    {
        return [
            new ComponentHealthCheck(
                component_name: self::COMPONENT_NAME,
                component_type: ComponentType::COMPONENT,
                status: HealthStatus::Pass,
                time: $clock->now(),
                additional: [
                    'version' => \PHP_VERSION,
                    'logTrace' => $this->log_trace,
                ],
            ),
        ];
    }
}
