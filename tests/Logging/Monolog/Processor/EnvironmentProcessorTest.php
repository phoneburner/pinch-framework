<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Logging\Monolog\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Tests\Fixtures\MockEnvironment;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Processor\EnvironmentProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvironmentProcessorTest extends TestCase
{
    #[Test]
    public function addsContextNameWhenMissing(): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            stage: BuildStage::Development,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertSame('Test', $result->extra['context']);
    }

    #[Test]
    public function preservesExistingContext(): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            stage: BuildStage::Development,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: ['context' => 'existing'],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertSame('existing', $result->extra['context']);
    }

    #[Test]
    #[DataProvider('provideBuildStages')]
    public function addsBuildStageWhenMissing(BuildStage $stage): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            stage: $stage,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertSame($stage->value, $result->extra['build_stage']);
    }

    #[Test]
    public function preservesExistingBuildStage(): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            stage: BuildStage::Production,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: ['build_stage' => 'existing'],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertSame('existing', $result->extra['build_stage']);
    }

    #[Test]
    public function doesNotAddGitCommitInDevelopment(): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            stage: BuildStage::Development,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertArrayNotHasKey('git_commit', $result->extra);
    }

    #[Test]
    #[DataProvider('provideNonDevelopmentStages')]
    public function addsGitCommitInNonDevelopment(BuildStage $stage): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            env: ['PINCH_GIT_COMMIT' => 'abcdef1234567890'],
            stage: $stage,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertSame('abcdef1', $result->extra['git_commit']);
    }

    #[Test]
    public function usesUnknownWhenGitCommitIsNull(): void
    {
        $environment = new MockEnvironment(
            root: '/app',
            stage: BuildStage::Production,
            context: Context::Test,
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );

        $processor = new EnvironmentProcessor($environment);
        $result = $processor($record);

        self::assertSame('unknown', $result->extra['git_commit']);
    }

    public static function provideBuildStages(): \Generator
    {
        yield 'Development' => [BuildStage::Development];
        yield 'Integration' => [BuildStage::Integration];
        yield 'Production' => [BuildStage::Production];
    }

    public static function provideNonDevelopmentStages(): \Generator
    {
        yield 'Integration' => [BuildStage::Integration];
        yield 'Production' => [BuildStage::Production];
    }
}
