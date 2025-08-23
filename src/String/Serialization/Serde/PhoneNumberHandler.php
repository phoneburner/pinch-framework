<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\String\Serialization\Serde;

use Crell\Serde\Attributes\Field;
use Crell\Serde\Deserializer;
use Crell\Serde\PropertyHandler\Exporter;
use Crell\Serde\PropertyHandler\Importer;
use Crell\Serde\Serializer;
use PhoneBurner\Pinch\Component\PhoneNumber\E164;
use PhoneBurner\Pinch\Component\PhoneNumber\PhoneNumber;

class PhoneNumberHandler implements Importer, Exporter
{
    #[\Override]
    public function canExport(Field $field, mixed $value, string $format): bool
    {
        return match ($field->phpType) {
            PhoneNumber::class, E164::class => true,
            default => false,
        };
    }

    #[\Override]
    public function exportValue(Serializer $serializer, Field $field, mixed $value, mixed $runningValue): mixed
    {
        \assert($value instanceof PhoneNumber);
        return $serializer->formatter->serializeString($runningValue, $field, (string)$value->toE164());
    }

    #[\Override]
    public function canImport(Field $field, string $format): bool
    {
        return match ($field->phpType) {
            PhoneNumber::class, E164::class => true,
            default => false,
        };
    }

    #[\Override]
    public function importValue(Deserializer $deserializer, Field $field, mixed $source): E164
    {
        return E164::tryFrom(
            $deserializer->deformatter->deserializeString($source, $field),
        ) ?? throw new \UnexpectedValueException("Invalid Phone Number");
    }
}
