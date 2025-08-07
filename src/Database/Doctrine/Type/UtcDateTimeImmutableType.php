<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PhoneBurner\Pinch\Time\TimeZone\Tz;

class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    public const string NAME = 'utc_datetime_immutable';

    /**
     * Only convert to UTC if the value is not already in UTC. We make the assumption
     * that if the offset from UTC is zero, the value is already in UTC. That should
     * be good enough for most use cases, at least where the value is being serialized
     * to a database.
     *
     * @param T $value
     * @return (T is null ? null : string)
     * @template T
     */
    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        static $utc = Tz::Utc->timezone();

        return match (true) {
            $value === null => null,
            $value instanceof \DateTimeImmutable => match ($value->getOffset()) {
                0 => $value->format($platform->getDateTimeFormatString()),
                default => $value->setTimezone($utc)->format($platform->getDateTimeFormatString()),
            },
            default => throw InvalidType::new($value, static::class, ['null', \DateTimeImmutable::class]),
        };
    }

    /**
     * @param T $value
     * @return (T is null ? null : \DateTimeImmutable)
     * @template T
     */
    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): \DateTimeImmutable|null
    {
        static $utc = Tz::Utc->timezone();

        try {
            return match (true) {
                $value === null => null,
                $value instanceof \DateTimeImmutable => $value->getOffset() === 0 ? $value : $value->setTimezone($utc),
                \is_string($value) => \DateTimeImmutable::createFromFormat(
                    $platform->getDateTimeFormatString(),
                    $value,
                    $utc,
                ) ?: new \DateTimeImmutable($value)->setTimezone($utc),
                default => throw new \InvalidArgumentException(
                    'Expected value to be null, DateTimeImmutable, or string, got ' . \get_debug_type($value),
                ),
            };
        } catch (\Exception $e) {
            throw InvalidFormat::new($value, static::class, $platform->getDateTimeFormatString(), $e);
        }
    }
}
