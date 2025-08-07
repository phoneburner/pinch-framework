<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use PhoneBurner\Pinch\Component\PhoneNumber\DomesticPhoneNumber;
use PhoneBurner\Pinch\Component\PhoneNumber\E164;
use PhoneBurner\Pinch\Component\PhoneNumber\PhoneNumber;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Types;

class PhoneNumberType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return Types::PHONE_NUMBER;
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $value->toE164()->value;
        }

        throw InvalidType::new(
            $value,
            $this->getName(),
            [PhoneNumber::class],
        );
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): PhoneNumber
    {
        return DomesticPhoneNumber::tryFrom($value) ?? E164::make($value);
    }
}
