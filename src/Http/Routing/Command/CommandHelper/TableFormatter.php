<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper;

use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class TableFormatter extends RouteDefinitionListFormatter
{
    public const string FORMAT = 'table';

    #[\Override]
    public function render(
        OutputInterface $output,
        DefinitionList $definitions,
        bool $show_attributes = true,
        bool $show_namespaces = true,
    ): int {
        $headers = self::HEADER_FIELDS;
        if (! $show_attributes) {
            $headers['attributes'] = 'NAME';
        }

        $table = new Table($output);
        $table->setHeaders($headers);

        foreach ($definitions as $definition) {
            $attributes = self::formatAttributes($definition, $show_attributes, $show_namespaces);
            $table->addRow([
                \count($definition->getMethods()) === 9 ? self::ALL_METHODS : \implode('|', $definition->getMethods()),
                $definition->getRoutePath(),
                new TableCell(\implode(\PHP_EOL, $attributes)),
            ]);

            if ($show_attributes) {
                $table->addRow(new TableSeparator());
            }
        }

        $table->render();
        $output->writeln('');

        return Command::SUCCESS;
    }
}
