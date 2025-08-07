<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use PhoneBurner\Pinch\Component\PhoneNumber\E164;
use PhoneBurner\Pinch\Component\PhoneNumber\NullablePhoneNumber;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Types;

class NullablePhoneNumberType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return Types::NULLABLE_PHONE_NUMBER;
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof NullablePhoneNumber) {
            return (string)$value->toE164() ?: null;
        }

        throw InvalidType::new(
            $value,
            $this->getName(),
            ['null', NullablePhoneNumber::class],
        );
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): E164|null
    {
        return $value ? E164::tryFrom($value) : null;
    }
}
