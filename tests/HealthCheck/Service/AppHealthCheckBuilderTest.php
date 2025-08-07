<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\Service;

use Carbon\CarbonImmutable;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Framework\HealthCheck\Service\AppHealthCheckBuilder;
use PhoneBurner\Pinch\Time\Clock\Clock;
use PhoneBurner\Pinch\Time\Clock\StaticClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AppHealthCheckBuilderTest extends TestCase
{
    #[Test]
    public function happyPath(): void
    {
        $now = new CarbonImmutable();
        $clock = new StaticClock($now);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $services = [
            new class () implements ComponentHealthCheckService {
                public function __invoke(Clock $clock): array
                {
                    return [
                        new ComponentHealthCheck(
                            component_name: 'rabbitmq',
                            measurement_name: 'messages',
                            component_type: 'datastore',
                            observed_value: 3,
                            status: HealthStatus::Pass,
                            time: $clock->now(),
                        ),
                        new ComponentHealthCheck(
                            component_name: 'rabbitmq',
                            measurement_name: 'responseTime',
                            component_type: 'datastore',
                            observed_value: 0.1,
                            observed_unit: 'ms',
                            status: HealthStatus::Pass,
                            time: $clock->now(),
                        ),
                    ];
                }
            },
            new class () implements ComponentHealthCheckService {
                public function __invoke(Clock $clock): array
                {
                    return [
                        new ComponentHealthCheck(
                            component_name: 'redis',
                            measurement_name: 'connections',
                            component_type: 'datastore',
                            observed_value: 3,
                            status: HealthStatus::Pass,
                            time: $clock->now(),
                        ),
                        new ComponentHealthCheck(
                            component_name: 'redis',
                            measurement_name: 'responseTime',
                            component_type: 'datastore',
                            observed_value: 0.1,
                            observed_unit: 'ms',
                            status: HealthStatus::Pass,
                            time: $clock->now(),
                        ),
                    ];
                }
            },
        ];

        $sut = new AppHealthCheckBuilder($clock, $logger)
            ->withDescription('Test Description')
            ->withLinks(['self' => '/health'])
            ->withServices(...$services);

        self::assertEquals(new HealthCheck(
            status: null,
            checks: [
                new ComponentHealthCheck(
                    component_name: 'rabbitmq',
                    measurement_name: 'messages',
                    component_type: 'datastore',
                    observed_value: 3,
                    status: HealthStatus::Pass,
                    time: $now,
                ),
                new ComponentHealthCheck(
                    component_name: 'rabbitmq',
                    measurement_name: 'responseTime',
                    component_type: 'datastore',
                    observed_value: 0.1,
                    observed_unit: 'ms',
                    status: HealthStatus::Pass,
                    time: $now,
                ),
                new ComponentHealthCheck(
                    component_name: 'redis',
                    measurement_name: 'connections',
                    component_type: 'datastore',
                    observed_value: 3,
                    status: HealthStatus::Pass,
                    time: $now,
                ),
                new ComponentHealthCheck(
                    component_name: 'redis',
                    measurement_name: 'responseTime',
                    component_type: 'datastore',
                    observed_value: 0.1,
                    observed_unit: 'ms',
                    status: HealthStatus::Pass,
                    time: $now,
                ),
            ],
            links: ['self' => '/health'],
            description: 'Test Description',
        ), $sut->make());
    }

    #[Test]
    public function sadPathCatchesExceptions(): void
    {
        $exception = new \RuntimeException('test exception');

        $now = new CarbonImmutable();
        $clock = new StaticClock($now);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Health Check Factory Failure', [
                'exception' => $exception,
            ]);

        $services = [
            new class () implements ComponentHealthCheckService {
                public function __invoke(Clock $clock): array
                {
                    throw new \RuntimeException('test exception');
                }
            },
        ];

        $sut = new AppHealthCheckBuilder($clock, $logger)
            ->withDescription('Test Description')
            ->withLinks(['self' => '/health'])
            ->withServices(...$services);

        self::assertEquals(new HealthCheck(
            status: HealthStatus::Fail,
            checks: [],
            links: ['self' => '/health'],
            description: 'Test Description',
        ), $sut->make());
    }
}
