<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\Domain;

use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class HealthCheckTest extends TestCase
{
    #[Test]
    #[TestWith([HealthStatus::Pass, HealthStatus::Pass])]
    #[TestWith([HealthStatus::Warn, HealthStatus::Warn])]
    #[TestWith([HealthStatus::Fail, HealthStatus::Fail])]
    #[TestWith([null, HealthStatus::Pass])]
    public function happyPathWithEmptyCase(HealthStatus|null $status, HealthStatus $expected): void
    {
        $health_check = new HealthCheck(status: $status);
        self::assertSame($expected, $health_check->status);
        self::assertSame([], $health_check->checks);
        self::assertNull($health_check->version);
        self::assertNull($health_check->release_id);
        self::assertSame([], $health_check->notes);
        self::assertNull($health_check->output);
        self::assertSame([], $health_check->links);
        self::assertNull($health_check->service_id);
        self::assertNull($health_check->description);

        self::assertSame([
            'status' => $expected,
        ], $health_check->jsonSerialize());
    }

    #[Test]
    public function happyPathWithAllProperties(): void
    {
        $component_check = new ComponentHealthCheck(
            component_name: 'x_component',
            measurement_name: 'x_measurement',
            component_id: 'x_component_id',
            component_type: 'x_component_type',
            observed_value: 'x_observed_value',
            observed_unit: 'x_observed_unit',
            status: HealthStatus::Pass,
            affected_endpoints: ['x_test_endpoint_1', 'x_test_endpoint_2'],
            time: new \DateTimeImmutable(),
            output: 'x_output',
            links: ['self' => '/test/x_component/healthz'],
            additional: ['additional_1' => 'test_additional_1', 'additional_2' => 'test_additional_2'],
        );

        $health_check = new HealthCheck(
            status: HealthStatus::Pass,
            version: 'x_version',
            release_id: 'x_release_id',
            notes: ['x_note_1', 'x_note_2'],
            output: 'x_output',
            checks: [$component_check],
            links: ['self' => '/test/x_component/healthz'],
            service_id: 'x_service_id',
            description: 'x_description',
        );

        self::assertSame(HealthStatus::Pass, $health_check->status);
        self::assertSame(['x_component:x_measurement' => [$component_check]], $health_check->checks);
        self::assertSame('x_version', $health_check->version);
        self::assertSame('x_release_id', $health_check->release_id);
        self::assertSame(['x_note_1', 'x_note_2'], $health_check->notes);
        self::assertSame('x_output', $health_check->output);
        self::assertSame(['self' => '/test/x_component/healthz'], $health_check->links);
        self::assertSame('x_service_id', $health_check->service_id);
        self::assertSame('x_description', $health_check->description);

        self::assertSame([
            'status' => HealthStatus::Pass,
            'version' => 'x_version',
            'releaseId' => 'x_release_id',
            'notes' => ['x_note_1', 'x_note_2'],
            'output' => 'x_output',
            'checks' => ['x_component:x_measurement' => [$component_check]],
            'links' => ['self' => '/test/x_component/healthz'],
            'serviceId' => 'x_service_id',
            'description' => 'x_description',
        ], $health_check->jsonSerialize());
    }

    #[Test]
    #[TestWith([HealthStatus::Pass, HealthStatus::Pass])]
    #[TestWith([HealthStatus::Warn, HealthStatus::Warn])]
    #[TestWith([HealthStatus::Fail, HealthStatus::Fail])]
    public function overallStatusCanBeDerivedFromComponentChecks(
        HealthStatus $component_status,
        HealthStatus $expected_status,
    ): void {
        $component_check_1 = new ComponentHealthCheck(
            component_name: 'x_component',
            measurement_name: 'x_measurement',
            component_id: 'x_component_id',
            component_type: 'x_component_type',
            observed_value: 'x_observed_value',
            observed_unit: 'x_observed_unit',
            status: HealthStatus::Pass,
            affected_endpoints: ['x_test_endpoint_1', 'x_test_endpoint_2'],
            time: new \DateTimeImmutable(),
            output: 'x_output',
            links: ['self' => '/test/x_component/healthz'],
            additional: ['additional_1' => 'test_additional_1', 'additional_2' => 'test_additional_2'],
        );

        $component_check_2 = new ComponentHealthCheck(
            component_name: 'x_component',
            measurement_name: 'x_measurement',
            component_id: 'x_component_id',
            component_type: 'x_component_type',
            observed_value: 'x_observed_value',
            observed_unit: 'x_observed_unit',
            status: $component_status,
            affected_endpoints: ['x_test_endpoint_1', 'x_test_endpoint_2'],
            time: new \DateTimeImmutable(),
            output: 'x_output',
            links: ['self' => '/test/x_component/healthz'],
            additional: ['additional_1' => 'test_additional_1', 'additional_2' => 'test_additional_2'],
        );

        $component_check_3 = new ComponentHealthCheck(
            component_name: 'z_component',
            component_id: 'x_component_id',
            component_type: 'x_component_type',
            observed_value: 'x_observed_value',
            observed_unit: 'x_observed_unit',
            status: HealthStatus::Pass,
            affected_endpoints: ['x_test_endpoint_1', 'x_test_endpoint_2'],
            time: new \DateTimeImmutable(),
            output: 'x_output',
            links: ['self' => '/test/x_component/healthz'],
            additional: ['additional_1' => 'test_additional_1', 'additional_2' => 'test_additional_2'],
        );

        $health_check = new HealthCheck(
            version: 'x_version',
            release_id: 'x_release_id',
            notes: ['x_note_1', 'x_note_2'],
            output: 'x_output',
            checks: [$component_check_1, $component_check_2, $component_check_3],
            links: ['self' => '/test/x_component/healthz'],
            service_id: 'x_service_id',
            description: 'x_description',
        );

        self::assertSame($expected_status, $health_check->status);
        self::assertSame([
            'x_component:x_measurement' => [
                $component_check_1,
                $component_check_2,
            ],
            'z_component' => [
            $component_check_3,
            ],
        ], $health_check->checks);
        self::assertSame('x_version', $health_check->version);
        self::assertSame('x_release_id', $health_check->release_id);
        self::assertSame(['x_note_1', 'x_note_2'], $health_check->notes);
        self::assertSame('x_output', $health_check->output);
        self::assertSame(['self' => '/test/x_component/healthz'], $health_check->links);
        self::assertSame('x_service_id', $health_check->service_id);
        self::assertSame('x_description', $health_check->description);

        self::assertSame([
            'status' => $expected_status,
            'version' => 'x_version',
            'releaseId' => 'x_release_id',
            'notes' => ['x_note_1', 'x_note_2'],
            'output' => 'x_output',
            'checks' => [
                'x_component:x_measurement' => [
                    $component_check_1,
                    $component_check_2,
                ],
                'z_component' => [
                    $component_check_3,
                ],
            ],
            'links' => ['self' => '/test/x_component/healthz'],
            'serviceId' => 'x_service_id',
            'description' => 'x_description',
        ], $health_check->jsonSerialize());
    }
}
