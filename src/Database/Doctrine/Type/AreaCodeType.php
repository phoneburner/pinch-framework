<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use PhoneBurner\Pinch\Component\PhoneNumber\AreaCode\AreaCode;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Types;

class AreaCodeType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return Types::AREA_CODE;
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AreaCode) {
            return (string)$value;
        }

        throw InvalidType::new(
            $value,
            $this->getName(),
            ['null', AreaCode::class],
        );
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): AreaCode|null
    {
        return $value ? AreaCode::make((int)$value) : null;
    }
}
