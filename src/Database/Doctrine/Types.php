<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine;

use Doctrine\DBAL\Types\Types as DoctrineTypes;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Type\AreaCodeType;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Type\NullablePhoneNumberType;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Type\PhoneNumberType;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Doctrine\UuidType;

final class Types
{
    // Built In Doctrine Types
    public const string ASCII_STRING = DoctrineTypes::ASCII_STRING;
    public const string BIGINT = DoctrineTypes::BIGINT;
    public const string BINARY = DoctrineTypes::BINARY;
    public const string BLOB = DoctrineTypes::BLOB;
    public const string BOOLEAN = DoctrineTypes::BOOLEAN;
    public const string DATE_MUTABLE = DoctrineTypes::DATE_MUTABLE;
    public const string DATE_IMMUTABLE = DoctrineTypes::DATE_IMMUTABLE;
    public const string DATEINTERVAL = DoctrineTypes::DATEINTERVAL;
    public const string DATETIME_MUTABLE = DoctrineTypes::DATETIME_MUTABLE;
    public const string DATETIME_IMMUTABLE = DoctrineTypes::DATETIME_IMMUTABLE;
    public const string DATETIMETZ_MUTABLE = DoctrineTypes::DATETIMETZ_MUTABLE;
    public const string DATETIMETZ_IMMUTABLE = DoctrineTypes::DATETIMETZ_IMMUTABLE;
    public const string DECIMAL = DoctrineTypes::DECIMAL;
    public const string FLOAT = DoctrineTypes::FLOAT;
    public const string GUID = DoctrineTypes::GUID;
    public const string INTEGER = DoctrineTypes::INTEGER;
    public const string JSON = DoctrineTypes::JSON;
    public const string SIMPLE_ARRAY = DoctrineTypes::SIMPLE_ARRAY;
    public const string SMALLINT = DoctrineTypes::SMALLINT;
    public const string STRING = DoctrineTypes::STRING;
    public const string TEXT = DoctrineTypes::TEXT;
    public const string TIME_MUTABLE = DoctrineTypes::TIME_MUTABLE;
    public const string TIME_IMMUTABLE = DoctrineTypes::TIME_IMMUTABLE;

    // Custom Types
    public const string BINARY_UUID = 'uuid_binary';
    public const string STRING_UUID = 'uuid_string';
    public const string NULLABLE_PHONE_NUMBER = 'nullable_phone_number';
    public const string PHONE_NUMBER = 'phone_number';
    public const string AREA_CODE = 'area_code';

    /** @var array<string, class-string> */
    public const array REGISTRATION_MAP = [
        // Register Vendor Doctrine Types
        self::BINARY_UUID => UuidBinaryType::class, // Use with BINARY(16) columns
        self::STRING_UUID => UuidType::class, // Use with CHAR(36) columns

        // Register Framework Doctrine Types
        self::NULLABLE_PHONE_NUMBER => NullablePhoneNumberType::class,
        self::PHONE_NUMBER => PhoneNumberType::class,
        self::AREA_CODE => AreaCodeType::class,
    ];
}
