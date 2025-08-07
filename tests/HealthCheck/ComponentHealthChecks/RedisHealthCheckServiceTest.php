<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\ComponentHealthChecks;

use Carbon\CarbonImmutable;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\RedisHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Time\Clock\StaticClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RedisHealthCheckServiceTest extends TestCase
{
    #[Test]
    public function happyPath(): void
    {
        $now = new CarbonImmutable();
        $clock = new StaticClock($now);
        $log_trace = LogTrace::make();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn(['client1', 'client2', 'client3']);

        $redis_manager = $this->createMock(RedisManager::class);
        $redis_manager->expects($this->once())
            ->method('connect')
            ->willReturn($redis);

        $sut = new RedisHealthCheckService($redis_manager, $log_trace, $logger);

        $response = $sut($clock);

        self::assertEquals([
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
                'component' => 'redis',
                'exception' => $exception,
            ]);

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException($exception);

        $redis_manager = $this->createMock(RedisManager::class);
        $redis_manager->expects($this->once())
            ->method('connect')
            ->willReturn($redis);

        $sut = new RedisHealthCheckService($redis_manager, $log_trace, $logger);

        $response = $sut($clock);

        self::assertEquals([
            new ComponentHealthCheck(
                component_name: 'redis',
                component_type: 'datastore',
                status: HealthStatus::Fail,
                time: $now,
                output: 'Health Check Failed (Log Trace: ' . $log_trace . ')',
            ),
        ], $response);
    }
}
