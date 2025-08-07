<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks;

use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentType;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\MeasurementName;
use PhoneBurner\Pinch\Time\Clock\Clock;
use PhoneBurner\Pinch\Time\Timer\StopWatch;
use Psr\Log\LoggerInterface;

class RedisHealthCheckService implements ComponentHealthCheckService
{
    public const string COMPONENT_NAME = 'redis';

    public function __construct(
        private readonly RedisManager $redis_manager,
        private readonly LogTrace $log_trace,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function __invoke(Clock $clock): array
    {
        $now = $clock->now();
        try {
            $redis = $this->redis_manager->connect();
            $timer = StopWatch::start();
            $connections = \count($redis->client('list'));
            $elapsed = $timer->elapsed();

            return [
                new ComponentHealthCheck(
                    component_name: self::COMPONENT_NAME,
                    measurement_name: MeasurementName::CONNECTIONS,
                    component_type: ComponentType::DATASTORE,
                    observed_value: $connections,
                    status: HealthStatus::Pass,
                    time: $now,
                ),
                new ComponentHealthCheck(
                    component_name: self::COMPONENT_NAME,
                    measurement_name: MeasurementName::RESPONSE_TIME,
                    component_type: ComponentType::DATASTORE,
                    observed_value: $elapsed->inMilliseconds(),
                    observed_unit: 'ms',
                    status: HealthStatus::Pass,
                    time: $now,
                ),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Health Check Failure: {component}', [
                'component' => self::COMPONENT_NAME,
                'exception' => $e,
            ]);

            return [
                new ComponentHealthCheck(
                    component_name: self::COMPONENT_NAME,
                    component_type: ComponentType::DATASTORE,
                    status: HealthStatus::Fail,
                    time: $now,
                    output: 'Health Check Failed (Log Trace: ' . $this->log_trace . ')',
                ),
            ];
        }
    }
}
