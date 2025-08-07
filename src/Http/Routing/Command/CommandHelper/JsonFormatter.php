<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper;

use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class JsonFormatter extends RouteDefinitionListFormatter
{
    public const string FORMAT = 'json';

    #[\Override]
    public function render(
        OutputInterface $output,
        DefinitionList $definitions,
        bool $show_attributes = true,
        bool $show_namespaces = true,
    ): int {
        $output->writeln(\json_encode([...$definitions], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
        return Command::SUCCESS;
    }
}
