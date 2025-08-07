<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\String\Serialization;

use PhoneBurner\Pinch\Filesystem\FileWriter;
use PhoneBurner\Pinch\String\Serialization\VarExporter as VarExporterContract;
use PhoneBurner\Pinch\Time\Standards\Rfc3339;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Export a value to opcache friendly PHP code, like var_export(), uses Symfony
 * VarExporter under the hood
 */
class SymfonyVarExporter implements VarExporterContract
{
    public const string FILE_EXPORT_TEMPLATE = <<<'PHP'
            <?php

            /**
             * %s (%s)
             */

            declare(strict_types=1);

            return %s;

            PHP;

    /**
     * Exports a value to a file, adding the PHP opening tag, a header message,
     * and a timestamp before returning the value.
     */
    public function file(
        \Stringable|string $filename,
        mixed $value,
        string $header_message = 'Generated File',
        \DateTimeInterface $timestamp = new \DateTimeImmutable(),
    ): bool {
        return FileWriter::string($filename, \sprintf(
            self::FILE_EXPORT_TEMPLATE,
            $header_message,
            $timestamp->format(Rfc3339::DATETIME),
            VarExporter::export($value),
        ));
    }

    public function string(mixed $value): string
    {
        return VarExporter::export($value);
    }
}
