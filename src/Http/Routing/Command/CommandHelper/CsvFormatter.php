<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper;

use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListFormatter;
use SplTempFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class CsvFormatter extends RouteDefinitionListFormatter
{
    public const string FORMAT = 'csv';

    #[\Override]
    public function render(
        OutputInterface $output,
        DefinitionList $definitions,
        bool $show_attributes = true,
        bool $show_namespaces = true,
    ): int {
        $headers = self::HEADER_FIELDS;

        $csv_file = new SplTempFileObject();
        $csv_file->fputcsv($headers, escape: '\\');

        foreach ($definitions as $definition) {
            $attributes = self::formatAttributes($definition);
            \sort($attributes);

            $csv_file->fputcsv([
                \count($definition->getMethods()) >= 9 ? self::ALL_METHODS : \implode(', ', $definition->getMethods()),
                $definition->getRoutePath(),
                \implode(\PHP_EOL, $attributes),
            ], escape: '\\');
        }

        $csv_file->rewind();
        $output->write([...$csv_file]);

        return Command::SUCCESS;
    }
}
