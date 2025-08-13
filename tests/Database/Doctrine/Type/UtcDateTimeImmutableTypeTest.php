<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Database\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Type\UtcDateTimeImmutableType;
use PhoneBurner\Pinch\Time\Standards\AnsiSql;
use PhoneBurner\Pinch\Time\TimeZone\Tz;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UtcDateTimeImmutableTypeTest extends TestCase
{
    private UtcDateTimeImmutableType $type;

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UtcDateTimeImmutableType();

        // Create a mock for AbstractPlatform
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->platform->method('getDateTimeFormatString')->willReturn(AnsiSql::DATETIME);
    }

    #[Test]
    public function convertToDatabaseValueReturnsNullForNullInput(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    #[Test]
    public function convertToDatabaseValueFormatsUtcDateTimeWithoutChangingTimezone(): void
    {
        $utc_datetime = new \DateTimeImmutable('2023-01-15 10:30:45', Tz::Utc->timezone());

        self::assertSame('2023-01-15 10:30:45', $this->type->convertToDatabaseValue($utc_datetime, $this->platform));
    }

    #[Test]
    public function convertToDatabaseValueConvertsNonUtcDateTimeToUtc(): void
    {
        // Create a datetime in a non-UTC timezone
        $non_utc_datetime = new \DateTimeImmutable('2023-01-15 10:30:45', Tz::LosAngeles->timezone());

        // Expected result is the UTC equivalent
        $expected = $non_utc_datetime->setTimezone(Tz::Utc->timezone())->format(AnsiSql::DATETIME);

        self::assertSame($expected, $this->type->convertToDatabaseValue($non_utc_datetime, $this->platform));
    }

    #[Test]
    public function convertToDatabaseValueThrowsExceptionForInvalidType(): void
    {
        $this->expectException(InvalidType::class);
        $this->type->convertToDatabaseValue('not a datetime', $this->platform);
    }

    #[Test]
    public function convertToPHPValueReturnsNullForNullInput(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    #[Test]
    public function convertToPHPValueReturnsSameDateTimeImmutableForUtcInput(): void
    {
        $utc_datetime = new \DateTimeImmutable('2023-01-15 10:30:45', Tz::Utc->timezone());

        self::assertSame($utc_datetime, $this->type->convertToPHPValue($utc_datetime, $this->platform));
    }

    #[Test]
    public function convertToPHPValueConvertsNonUtcDateTimeImmutableToUtc(): void
    {
        $non_utc_datetime = new \DateTimeImmutable('2023-01-15 10:30:45', Tz::LosAngeles->timezone());
        $expected = $non_utc_datetime->setTimezone(Tz::Utc->timezone());

        $result = $this->type->convertToPHPValue($non_utc_datetime, $this->platform);

        self::assertEquals($expected, $result);
        self::assertSame(0, $result->getOffset());
        self::assertSame('2023-01-15T18:30:45+00:00', $result->format('c'));
        self::assertSame('2023-01-15 18:30:45', $result->format(AnsiSql::DATETIME));
    }

    #[Test]
    public function convertToPHPValueParsesStringUsingPlatformFormat(): void
    {
        $datetime_string = '2023-01-15 10:30:45';
        $expected = \DateTimeImmutable::createFromFormat(
            AnsiSql::DATETIME,
            $datetime_string,
            Tz::Utc->timezone(),
        );

        $result = $this->type->convertToPHPValue($datetime_string, $this->platform);

        self::assertEquals($expected, $result);
        self::assertSame(0, $result->getOffset());
    }

    #[Test]
    public function convertToPHPValueFallsBackToDateTimeConstructorForInvalidFormat(): void
    {
        // A string that doesn't match the platform format but is still a valid datetime string
        $datetime_string = '2023-01-15T10:30:45Z';

        $result = $this->type->convertToPHPValue($datetime_string, $this->platform);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame(0, $result->getOffset());
        self::assertSame('2023-01-15T10:30:45+00:00', $result->format('c'));
    }

    #[Test]
    public function convertToPHPValueFallsBackToDateTimeConstructorForInvalidFormatWithTz(): void
    {
        // A string that doesn't match the platform format but is still a valid datetime string
        // with a timezone offset that is not UTC
        $datetime_string = '2025-07-21T16:11:45-05:00';

        $result = $this->type->convertToPHPValue($datetime_string, $this->platform);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame(0, $result->getOffset());
        self::assertSame('2025-07-21T21:11:45+00:00', $result->format('c'));
    }

    #[Test]
    public function convertToPHPValueThrowsExceptionForInvalidString(): void
    {
        $this->expectException(InvalidFormat::class);
        $this->type->convertToPHPValue('not a valid datetime', $this->platform);
    }

    #[Test]
    public function convertToPHPValueThrowsExceptionForInvalidType(): void
    {
        $this->expectException(\TypeError::class);
        $this->type->convertToPHPValue(123, $this->platform);
    }
}
