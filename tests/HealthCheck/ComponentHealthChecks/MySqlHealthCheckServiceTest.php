<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\ComponentHealthChecks;

use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\MySqlHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Time\Clock\StaticClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class MySqlHealthCheckServiceTest extends TestCase
{
    #[Test]
    public function happyPath(): void
    {
        $now = new CarbonImmutable();
        $clock = new StaticClock($now);
        $log_trace = LogTrace::make();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(MySqlHealthCheckService::SQL)
            ->willReturn('3');

        $sut = new MySqlHealthCheckService($connection, $log_trace, $logger);

        $response = $sut($clock);

        self::assertEquals([
            new ComponentHealthCheck(
                component_name: 'mysql',
                measurement_name: 'connections',
                component_type: 'datastore',
                observed_value: 3,
                status: HealthStatus::Pass,
                time: $now,
            ),
            new ComponentHealthCheck(
                component_name: 'mysql',
                measurement_name: 'responseTime',
                component_type: 'datastore',
                observed_value: $response[1]->observed_value,
                observed_unit: 'ms',
                status: HealthStatus::Pass,
                time: $now,
            ),
        ], $response);

        self::assertIsFloat($response[1]->observed_value);
    }

    #[Test]
    public function sadPathCatchesExceptions(): void
    {
        $exception = new \RuntimeException('test exception');

        $now = new CarbonImmutable();
        $clock = new StaticClock($now);
        $log_trace = LogTrace::make();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Health Check Failure: {component}', [
                'component' => 'mysql',
                'exception' => $exception,
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(MySqlHealthCheckService::SQL)
            ->willThrowException($exception);

        $sut = new MySqlHealthCheckService($connection, $log_trace, $logger);

        $response = $sut($clock);

        self::assertEquals([
            new ComponentHealthCheck(
                component_name: 'mysql',
                component_type: 'datastore',
                status: HealthStatus::Fail,
                time: $now,
                output: 'Health Check Failed (Log Trace: ' . $log_trace . ')',
            ),
        ], $response);
    }
}
