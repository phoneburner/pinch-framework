<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Configuration;

use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Framework\Configuration\Environment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    #[Test]
    public function environmentHasRoot(): void
    {
        $root = 'some/system/path';
        $server = [
            'some-enum' => BuildStage::Production,
        ];
        $env = [];
        $sut = new Environment(Context::Test, BuildStage::Production, $root, $server, $env);
        self::assertSame($root, $sut->root);
        self::assertSame(\gethostname(), $sut->hostname());
        self::assertSame(BuildStage::Production, $sut->server('some-enum'));
        self::assertSame(BuildStage::Production, $sut->get('some-enum'));
    }

    #[Test]
    public function environmentCanGetValuesFromTheEnvironmentAndServer(): void
    {
        $empty = [];
        $server = [
            'SERVER_DUMMY_00' => 'production',
            'SERVER_DUMMY_01' => 'null',
            'SERVER_DUMMY_02' => 'false',
            'SERVER_DUMMY_03' => 'true',
            'SERVER_DUMMY_04' => 'yes',
            'SERVER_DUMMY_05' => 'no',
            'SERVER_DUMMY_06' => 'on',
            'SERVER_DUMMY_07' => 'off',
            'SERVER_DUMMY_08' => '0',
            'SERVER_DUMMY_09' => '1',
            'SERVER_DUMMY_10' => '0.0',
            'SERVER_DUMMY_11' => '1.0',
            'SERVER_DUMMY_12' => '0.1',
            'SERVER_DUMMY_13' => '1.1',
            'SERVER_DUMMY_14' => 'string-value',
            'SERVER_DUMMY_15' => '',
            // 'SERVER_DUMMY_16' is null to test the default value
            // SAME_KEY_01'  is null to test fall-through value
            'SAME_KEY_02' => 'red',
        ];

        $env = [
            'ENV_DUMMY_00' => 'production',
            'ENV_DUMMY_01' => 'null',
            'ENV_DUMMY_02' => 'false',
            'ENV_DUMMY_03' => 'true',
            'ENV_DUMMY_04' => 'yes',
            'ENV_DUMMY_05' => 'no',
            'ENV_DUMMY_06' => 'on',
            'ENV_DUMMY_07' => 'off',
            'ENV_DUMMY_08' => '0',
            'ENV_DUMMY_09' => '1',
            'ENV_DUMMY_10' => '0.0',
            'ENV_DUMMY_11' => '1.0',
            'ENV_DUMMY_12' => '0.1',
            'ENV_DUMMY_13' => '1.1',
            'ENV_DUMMY_14' => 'string-value',
            'ENV_DUMMY_15' => '',
            // 'ENV_DUMMY_16' is undefined to test the default value
            'SAME_KEY_01' => 'blue',
            'SAME_KEY_02' => 'blue',
        ];

        $sut = new Environment(Context::Test, BuildStage::Production, '', $server, $empty);

        self::assertSame('production', $sut->server('SERVER_DUMMY_00'));
        self::assertNull($sut->server('SERVER_DUMMY_01'));
        self::assertFalse($sut->server('SERVER_DUMMY_02'));
        self::assertTrue($sut->server('SERVER_DUMMY_03'));
        self::assertTrue($sut->server('SERVER_DUMMY_04'));
        self::assertFalse($sut->server('SERVER_DUMMY_05'));
        self::assertTrue($sut->server('SERVER_DUMMY_06'));
        self::assertFalse($sut->server('SERVER_DUMMY_07'));
        self::assertSame(0, $sut->server('SERVER_DUMMY_08'));
        self::assertSame(1, $sut->server('SERVER_DUMMY_09'));
        self::assertSame(0.0, $sut->server('SERVER_DUMMY_10'));
        self::assertSame(1.0, $sut->server('SERVER_DUMMY_11'));
        self::assertSame(0.1, $sut->server('SERVER_DUMMY_12'));
        self::assertSame(1.1, $sut->server('SERVER_DUMMY_13'));
        self::assertSame('string-value', $sut->server('SERVER_DUMMY_14'));
        self::assertNull($sut->server('SERVER_DUMMY_15'));
        self::assertNull($sut->server('SERVER_DUMMY_16'));

        foreach ($server as $key => $value) {
            self::assertTrue($sut->has($key));
            self::assertSame($value, $sut->get($key));
        }

        $sut = new Environment(Context::Test, BuildStage::Production, '', $empty, $env);

        self::assertSame('production', $sut->env('ENV_DUMMY_00'));
        self::assertNull($sut->env('ENV_DUMMY_01'));
        self::assertFalse($sut->env('ENV_DUMMY_02'));
        self::assertTrue($sut->env('ENV_DUMMY_03'));
        self::assertTrue($sut->env('ENV_DUMMY_04'));
        self::assertFalse($sut->env('ENV_DUMMY_05'));
        self::assertTrue($sut->env('ENV_DUMMY_06'));
        self::assertFalse($sut->env('ENV_DUMMY_07'));
        self::assertSame(0, $sut->env('ENV_DUMMY_08'));
        self::assertSame(1, $sut->env('ENV_DUMMY_09'));
        self::assertSame(0.0, $sut->env('ENV_DUMMY_10'));
        self::assertSame(1.0, $sut->env('ENV_DUMMY_11'));
        self::assertSame(0.1, $sut->env('ENV_DUMMY_12'));
        self::assertSame(1.1, $sut->env('ENV_DUMMY_13'));
        self::assertSame('string-value', $sut->env('ENV_DUMMY_14'));
        self::assertNull($sut->env('ENV_DUMMY_15'));
        self::assertNull($sut->env('ENV_DUMMY_16'));

        foreach ($env as $key => $value) {
            self::assertTrue($sut->has($key));
            self::assertSame($value, $sut->get($key));
        }

        $sut = new Environment(Context::Test, BuildStage::Integration, '', $server, $env);
        self::assertSame('production', $sut->server('SERVER_DUMMY_00', 'default-value'));
        self::assertSame('default-value', $sut->server('SERVER_DUMMY_01', 'default-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_02', 'default-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_03', 'default-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_04', 'default-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_05', 'default-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_06', 'default-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_07', 'default-value'));
        self::assertSame(0, $sut->server('SERVER_DUMMY_08', 'default-value'));
        self::assertSame(1, $sut->server('SERVER_DUMMY_09', 'default-value'));
        self::assertSame(0.0, $sut->server('SERVER_DUMMY_10', 'default-value'));
        self::assertSame(1.0, $sut->server('SERVER_DUMMY_11', 'default-value'));
        self::assertSame(0.1, $sut->server('SERVER_DUMMY_12', 'default-value'));
        self::assertSame(1.1, $sut->server('SERVER_DUMMY_13', 'default-value'));
        self::assertSame('string-value', $sut->server('SERVER_DUMMY_14', 'default-value'));
        self::assertSame('default-value', $sut->server('SERVER_DUMMY_15', 'default-value'));
        self::assertSame('default-value', $sut->server('SERVER_DUMMY_16', 'default-value'));
        self::assertSame('production', $sut->env('ENV_DUMMY_00', 'default-value'));
        self::assertSame('default-value', $sut->env('ENV_DUMMY_01', 'default-value'));
        self::assertFalse($sut->env('ENV_DUMMY_02', 'default-value'));
        self::assertTrue($sut->env('ENV_DUMMY_03', 'default-value'));
        self::assertTrue($sut->env('ENV_DUMMY_04', 'default-value'));
        self::assertFalse($sut->env('ENV_DUMMY_05', 'default-value'));
        self::assertTrue($sut->env('ENV_DUMMY_06', 'default-value'));
        self::assertFalse($sut->env('ENV_DUMMY_07', 'default-value'));
        self::assertSame(0, $sut->env('ENV_DUMMY_08', 'default-value'));
        self::assertSame(1, $sut->env('ENV_DUMMY_09', 'default-value'));
        self::assertSame(0.0, $sut->env('ENV_DUMMY_10', 'default-value'));
        self::assertSame(1.0, $sut->env('ENV_DUMMY_11', 'default-value'));
        self::assertSame(0.1, $sut->env('ENV_DUMMY_12', 'default-value'));
        self::assertSame(1.1, $sut->env('ENV_DUMMY_13', 'default-value'));
        self::assertSame('string-value', $sut->env('ENV_DUMMY_14', 'default-value'));
        self::assertSame('default-value', $sut->env('ENV_DUMMY_15', 'default-value'));
        self::assertSame('default-value', $sut->env('ENV_DUMMY_16', 'default-value'));

        foreach ($server as $key => $value) {
            self::assertTrue($sut->has($key));
            self::assertSame($value, $sut->get($key));
        }

        self::assertFalse($sut->has('SERVER_DUMMY_16'));
        self::assertFalse($sut->has('ENV_DUMMY_16'));
        self::assertNull($sut->get('SERVER_DUMMY_16'));

        self::assertTrue($sut->has('SAME_KEY_01'));
        self::assertTrue($sut->has('SAME_KEY_02'));
        self::assertSame('blue', $sut->get('SAME_KEY_01'));
        self::assertSame('red', $sut->get('SAME_KEY_02'));
        self::assertNull($sut->get('SAME_KEY_03'));

        $sut = new Environment(Context::Test, BuildStage::Integration, '', $server, $env);
        self::assertSame('production', $sut->server('SERVER_DUMMY_00', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('integration-value', $sut->server('SERVER_DUMMY_01', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_02', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_03', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_04', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_05', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_06', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_07', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0, $sut->server('SERVER_DUMMY_08', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1, $sut->server('SERVER_DUMMY_09', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.0, $sut->server('SERVER_DUMMY_10', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.0, $sut->server('SERVER_DUMMY_11', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.1, $sut->server('SERVER_DUMMY_12', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.1, $sut->server('SERVER_DUMMY_13', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('string-value', $sut->server('SERVER_DUMMY_14', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('integration-value', $sut->server('SERVER_DUMMY_15', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('integration-value', $sut->server('SERVER_DUMMY_16', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('production', $sut->env('ENV_DUMMY_00', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('integration-value', $sut->env('ENV_DUMMY_01', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->env('ENV_DUMMY_02', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->env('ENV_DUMMY_03', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->env('ENV_DUMMY_04', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->env('ENV_DUMMY_05', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->env('ENV_DUMMY_06', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->env('ENV_DUMMY_07', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0, $sut->env('ENV_DUMMY_08', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1, $sut->env('ENV_DUMMY_09', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.0, $sut->env('ENV_DUMMY_10', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.0, $sut->env('ENV_DUMMY_11', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.1, $sut->env('ENV_DUMMY_12', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.1, $sut->env('ENV_DUMMY_13', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('string-value', $sut->env('ENV_DUMMY_14', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('integration-value', $sut->env('ENV_DUMMY_15', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('integration-value', $sut->env('ENV_DUMMY_16', 'default-value', 'development-value', 'integration-value'));

        $sut = new Environment(Context::Test, BuildStage::Development, '', $server, $env);
        self::assertSame('production', $sut->server('SERVER_DUMMY_00', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('development-value', $sut->server('SERVER_DUMMY_01', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_02', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_03', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_04', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_05', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->server('SERVER_DUMMY_06', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->server('SERVER_DUMMY_07', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0, $sut->server('SERVER_DUMMY_08', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1, $sut->server('SERVER_DUMMY_09', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.0, $sut->server('SERVER_DUMMY_10', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.0, $sut->server('SERVER_DUMMY_11', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.1, $sut->server('SERVER_DUMMY_12', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.1, $sut->server('SERVER_DUMMY_13', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('string-value', $sut->server('SERVER_DUMMY_14', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('development-value', $sut->server('SERVER_DUMMY_15', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('development-value', $sut->server('SERVER_DUMMY_16', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('production', $sut->env('ENV_DUMMY_00', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('development-value', $sut->env('ENV_DUMMY_01', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->env('ENV_DUMMY_02', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->env('ENV_DUMMY_03', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->env('ENV_DUMMY_04', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->env('ENV_DUMMY_05', 'default-value', 'development-value', 'integration-value'));
        self::assertTrue($sut->env('ENV_DUMMY_06', 'default-value', 'development-value', 'integration-value'));
        self::assertFalse($sut->env('ENV_DUMMY_07', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0, $sut->env('ENV_DUMMY_08', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1, $sut->env('ENV_DUMMY_09', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.0, $sut->env('ENV_DUMMY_10', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.0, $sut->env('ENV_DUMMY_11', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(0.1, $sut->env('ENV_DUMMY_12', 'default-value', 'development-value', 'integration-value'));
        self::assertSame(1.1, $sut->env('ENV_DUMMY_13', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('string-value', $sut->env('ENV_DUMMY_14', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('development-value', $sut->env('ENV_DUMMY_15', 'default-value', 'development-value', 'integration-value'));
        self::assertSame('development-value', $sut->env('ENV_DUMMY_16', 'default-value', 'development-value', 'integration-value'));
    }
}
