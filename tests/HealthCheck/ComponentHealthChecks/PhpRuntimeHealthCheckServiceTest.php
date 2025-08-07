<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\ComponentHealthChecks;

use Carbon\CarbonImmutable;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\PhpRuntimeHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Time\Clock\StaticClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhpRuntimeHealthCheckServiceTest extends TestCase
{
    #[Test]
    public function happyPath(): void
    {
        $now = new CarbonImmutable();
        $clock = new StaticClock($now);
        $log_trace = LogTrace::make();

        $sut = new PhpRuntimeHealthCheckService($log_trace);

        self::assertEquals([new ComponentHealthCheck(
            component_name: 'php',
            component_type: 'component',
            status: HealthStatus::Pass,
            time: $now,
            additional: [
                'version' => \PHP_VERSION,
                'logTrace' => $log_trace,
            ],
        )], $sut($clock));
    }
}
