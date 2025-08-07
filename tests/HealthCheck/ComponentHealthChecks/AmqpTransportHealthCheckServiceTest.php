<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\ComponentHealthChecks;

use Carbon\CarbonImmutable;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\AmqpTransportHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Time\Clock\StaticClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;

final class AmqpTransportHealthCheckServiceTest extends TestCase
{
    #[Test]
    public function happyPath(): void
    {
        $now = new CarbonImmutable();
        $clock = new StaticClock($now);
        $log_trace = LogTrace::make();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $transport = $this->createMock(AmqpTransport::class);
        $transport->expects($this->once())
            ->method('getMessageCount')
            ->willReturn(3);

        $sut = new AmqpTransportHealthCheckService($transport, $log_trace, $logger);

        $response = $sut($clock);

        self::assertEquals([
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
                'component' => 'rabbitmq',
                'exception' => $exception,
            ]);

        $transport = $this->createMock(AmqpTransport::class);
        $transport->expects($this->once())
            ->method('getMessageCount')
            ->willThrowException($exception);

        $sut = new AmqpTransportHealthCheckService($transport, $log_trace, $logger);

        $response = $sut($clock);

        self::assertEquals([
            new ComponentHealthCheck(
                component_name: 'rabbitmq',
                component_type: 'datastore',
                status: HealthStatus::Fail,
                time: $now,
                output: 'Health Check Failed (Log Trace: ' . $log_trace . ')',
            ),
        ], $response);
    }
}
