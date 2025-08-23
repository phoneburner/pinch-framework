<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\String\Serialization\Serde;

use Crell\Serde\Attributes\Field;
use Crell\Serde\Deserializer;
use Crell\Serde\PropertyHandler\Exporter;
use Crell\Serde\PropertyHandler\Importer;
use Crell\Serde\Serializer;
use PhoneBurner\Pinch\Component\PhoneNumber\AreaCode\AreaCode;

class AreaCodeHandler implements Importer, Exporter
{
    #[\Override]
    public function exportValue(Serializer $serializer, Field $field, mixed $value, mixed $runningValue): mixed
    {
        \assert($value instanceof AreaCode);
        return $serializer->formatter->serializeString($runningValue, $field, (string)$value);
    }

    #[\Override]
    public function canExport(Field $field, mixed $value, string $format): bool
    {
        return $field->phpType === AreaCode::class;
    }

    #[\Override]
    public function importValue(Deserializer $deserializer, Field $field, mixed $source): AreaCode
    {
        return AreaCode::tryFrom(
            $deserializer->deformatter->deserializeString($source, $field),
        ) ?? throw new \UnexpectedValueException("Invalid NPA");
    }

    #[\Override]
    public function canImport(Field $field, string $format): bool
    {
        return $field->phpType === AreaCode::class;
    }
}
