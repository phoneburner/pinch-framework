<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Logging\Monolog\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use PhoneBurner\Pinch\Component\PhoneNumber\DomesticPhoneNumber;
use PhoneBurner\Pinch\Component\PhoneNumber\NullPhoneNumber;
use PhoneBurner\Pinch\Framework\Logging\Monolog\Processor\PhoneNumberProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhoneNumberProcessorTest extends TestCase
{
    #[Test]
    public function contextWithoutPhoneNumber(): void
    {
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'test-channel',
            Level::Debug,
            'this is a test message',
            [
                'foo' => 'bar',
                'member' => new \stdClass(),
            ],
        );

        $sut = new PhoneNumberProcessor();

        self::assertSame($record, $sut($record));
    }

    #[Test]
    public function contextWithNullPhoneNumber(): void
    {
        $now = new \DateTimeImmutable();
        $record = new LogRecord(
            $now,
            'test-channel',
            Level::Debug,
            'this is a test message',
            [
                'foo' => 'bar',
                'phone_number' => new NullPhoneNumber(),
                'member' => new \stdClass(),
            ],
        );

        $sut = new PhoneNumberProcessor();

        self::assertEquals(new LogRecord(
            $now,
            'test-channel',
            Level::Debug,
            'this is a test message',
            [
                'foo' => 'bar',
                'phone_number' => null,
                'member' => new \stdClass(),
            ],
        ), $sut($record));
    }

    #[Test]
    public function contextWithPhoneNumber(): void
    {
        $now = new \DateTimeImmutable();
        $record = new LogRecord(
            $now,
            'test-channel',
            Level::Debug,
            'this is a test message',
            [
                'foo' => 'bar',
                'phone_number' => DomesticPhoneNumber::make('3145551234'),
                'member' => new \stdClass(),
            ],
        );

        $sut = new PhoneNumberProcessor();

        self::assertEquals(new LogRecord(
            $now,
            'test-channel',
            Level::Debug,
            'this is a test message',
            [
                'foo' => 'bar',
                'phone_number' => '+13145551234',
                'member' => new \stdClass(),
            ],
        ), $sut($record));
    }
}
