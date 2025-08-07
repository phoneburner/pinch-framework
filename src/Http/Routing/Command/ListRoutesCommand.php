<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command;

use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\InMemoryDefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\CsvFormatter;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\HashFormatter;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\JsonFormatter;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListFormatter;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListSorter;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\SortByName;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\SortByPath;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\TableFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

#[AsCommand(self::NAME, self::DESCRIPTION)]
class ListRoutesCommand extends Command
{
    public const string NAME = 'routing:list';

    public const string DESCRIPTION = 'List the routes defined in "includes/routes.php"';

    private const string SORT_ASC = 'asc';
    private const string SORT_DESC = 'desc';
    private const string ALL_METHODS = 'ALL';

    /**
     * @var array<string, class-string<RouteDefinitionListSorter>>
     */
    private const array SORTERS = [
        'name' => SortByName::class,
        'path' => SortByPath::class,
    ];

    /**
     * @var array<string, class-string<RouteDefinitionListFormatter>>
     */
    private const array FORMATTERS = [
        CsvFormatter::FORMAT => CsvFormatter::class,
        HashFormatter::FORMAT => HashFormatter::class,
        JsonFormatter::FORMAT => JsonFormatter::class,
        TableFormatter::FORMAT => TableFormatter::class,
    ];

    public function __construct(private readonly DefinitionList $definition_list)
    {
        parent::__construct(self::NAME);
        $this->setDescription(self::DESCRIPTION);

        $callback = static fn($string): string => \sprintf('<fg=yellow>"%s"</>', $string);

        $this->addOption('format', null, InputOption::VALUE_REQUIRED, \sprintf(<<<'EOF'
            Output Format for Routes Data (%s)
            EOF, \implode(', ', \array_map($callback, \array_keys(self::FORMATTERS)))), TableFormatter::FORMAT);

        $this->addOption('sort', null, InputOption::VALUE_REQUIRED, \sprintf(<<<'EOF'
            Sort Data By Field (%s)
            EOF, \implode(', ', \array_map($callback, \array_keys(self::SORTERS)))));

        $this->addOption('sort-dir', null, InputOption::VALUE_REQUIRED, <<<'EOF'
            Sort Direction (<fg=yellow>"asc"</>, <fg=yellow>"desc"</>)
            EOF, self::SORT_ASC);

        $values = \implode(', ', \array_map($callback, [self::ALL_METHODS, ...HttpMethod::values()]));
        $this->addOption('filter-method', null, InputOption::VALUE_REQUIRED, \sprintf(<<<'EOF'
            Filter Routes by HTTP Method (%s)
            EOF, $values), self::ALL_METHODS);

        $this->addOption('filter-path', null, InputOption::VALUE_REQUIRED, <<<'EOF'
            Filter Routes by Path String
            EOF,);

        $this->addOption('namespaces', null, InputOption::VALUE_NONE, <<<'EOF'
            Show Full Namespaces in Table View
            EOF,);

        $this->addOption('attributes', null, InputOption::VALUE_NONE, <<<'EOF'
            Show Attributes in Table View
            EOF,);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $definitions = $this->definition_list;
        $definitions = self::filterByHttpMethod($definitions, (string)$input->getOption('filter-method'));
        $definitions = self::filterByPath($definitions, $input->getOption('filter-path'));
        $definitions = self::sort($definitions, $input->getOption('sort'), $input->getOption('sort-dir'));

        return self::format((string)$input->getOption('format'))->render(
            $output,
            $definitions,
            (bool)$input->getOption('attributes'),
            (bool)$input->getOption('namespaces'),
        );
    }

    private static function filterByHttpMethod(DefinitionList $definitions, string $method): DefinitionList
    {
        if ($method !== self::ALL_METHODS && ! \in_array($method, HttpMethod::values(), true)) {
            throw new UnexpectedValueException('Invalid HTTP Method Value: ' . $method);
        }

        return $method === self::ALL_METHODS ? $definitions : InMemoryDefinitionList::make(...\array_filter(
            [...$definitions],
            static fn(RouteDefinition $definition): bool => \in_array($method, $definition->getMethods(), true),
        ));
    }

    private static function filterByPath(DefinitionList $definitions, mixed $path): DefinitionList
    {
        if ($path !== null && ! \is_string($path)) {
            throw new UnexpectedValueException('Invalid HTTP Filter Path Value');
        }

        return $path === null ? $definitions : InMemoryDefinitionList::make(...\array_filter(
            [...$definitions],
            static fn(RouteDefinition $definition): bool => \str_contains($definition->getRoutePath(), $path),
        ));
    }

    private static function sort(DefinitionList $definitions, string|null $sort, string $sort_dir): DefinitionList
    {
        if ($sort === null) {
            return $definitions;
        }

        $sort_dir = \strtolower($sort_dir);
        if ($sort_dir !== self::SORT_ASC && $sort_dir !== self::SORT_DESC) {
            throw new UnexpectedValueException('Invalid Sort Direction: ' . $sort_dir);
        }

        $sorter = self::SORTERS[$sort] ?? throw new UnexpectedValueException('Invalid Sort Value: ' . $sort);

        $definitions = [...$definitions];
        \usort($definitions, new $sorter($sort_dir === self::SORT_ASC));
        return InMemoryDefinitionList::make(...$definitions);
    }

    private static function format(string $format): RouteDefinitionListFormatter
    {
        $formatter = self::FORMATTERS[$format] ?? throw new UnexpectedValueException('Invalid Output Format:' . $format);
        return new $formatter();
    }
}
