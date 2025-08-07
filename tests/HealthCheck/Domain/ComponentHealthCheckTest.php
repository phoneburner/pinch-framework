<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\Domain;

use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Time\Standards\Rfc3339;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ComponentHealthCheckTest extends TestCase
{
    #[Test]
    #[TestWith([HealthStatus::Pass])]
    #[TestWith([HealthStatus::Warn])]
    #[TestWith([HealthStatus::Fail])]
    public function happyPathWithAllProperties(HealthStatus $status): void
    {
        $now = new \DateTimeImmutable();

        $health_check = new ComponentHealthCheck(
            component_name: 'x_component',
            measurement_name: 'x_measurement',
            component_id: 'x_component_id',
            component_type: 'x_component_type',
            observed_value: 'x_observed_value',
            observed_unit: 'x_observed_unit',
            status: $status,
            affected_endpoints: ['x_test_endpoint_1', 'x_test_endpoint_2'],
            time: $now,
            output: 'x_output',
            links: ['self' => '/test/x_component/healthz'],
            additional: ['additional_1' => 'test_additional_1', 'additional_2' => 'test_additional_2'],
        );

        self::assertSame('x_component:x_measurement', $health_check->name());
        self::assertSame([
            'componentId' => 'x_component_id',
            'componentType' => 'x_component_type',
            'observedValue' => 'x_observed_value',
            'observedUnit' => 'x_observed_unit',
            'status' => $status,
            'affectedEndpoints' => ['x_test_endpoint_1', 'x_test_endpoint_2'],
            'time' => $now->format(Rfc3339::DATETIME),
            'output' => 'x_output',
            'links' => ['self' => '/test/x_component/healthz'],
            'additional_1' => 'test_additional_1',
            'additional_2' => 'test_additional_2',
        ], $health_check->jsonSerialize());
    }

    #[Test]
    #[TestWith([HealthStatus::Pass])]
    #[TestWith([HealthStatus::Warn])]
    #[TestWith([HealthStatus::Fail])]
    public function happyPathWithSomeProperties(HealthStatus $status): void
    {
        $now = new \DateTimeImmutable();

        $health_check = new ComponentHealthCheck(
            component_name: 'x_component',
            measurement_name: 'x_measurement',
            component_type: 'x_component_type',
            observed_value: 'x_observed_value',
            observed_unit: 'x_observed_unit',
            status: $status,
            time: $now,
            links: ['self' => '/test/x_component/healthz'],
        );

        self::assertSame('x_component:x_measurement', $health_check->name());
        self::assertSame([
            'componentType' => 'x_component_type',
            'observedValue' => 'x_observed_value',
            'observedUnit' => 'x_observed_unit',
            'status' => $status,
            'time' => $now->format(Rfc3339::DATETIME),
            'links' => ['self' => '/test/x_component/healthz'],
        ], $health_check->jsonSerialize());
    }

    #[Test]
    #[TestWith([HealthStatus::Pass])]
    #[TestWith([HealthStatus::Warn])]
    #[TestWith([HealthStatus::Fail])]
    public function happyPathWithComponentOnly(HealthStatus $status): void
    {
        $now = new \DateTimeImmutable();

        $health_check = new ComponentHealthCheck(
            component_name: 'x_component',
            component_type: 'x_component_type',
            status: $status,
            time: $now,
            links: ['self' => '/test/x_component/healthz'],
        );

        self::assertSame('x_component', $health_check->name());
        self::assertSame([
            'componentType' => 'x_component_type',
            'status' => $status,
            'time' => $now->format(Rfc3339::DATETIME),
            'links' => ['self' => '/test/x_component/healthz'],
        ], $health_check->jsonSerialize());
    }

    #[Test]
    public function happyPathWithNoProperties(): void
    {
        $health_check = new ComponentHealthCheck();

        self::assertSame('', $health_check->name());
        self::assertSame([], $health_check->jsonSerialize());
    }
}
