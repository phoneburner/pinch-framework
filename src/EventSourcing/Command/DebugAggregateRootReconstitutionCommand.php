<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing\Command;

use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\AggregateRoot;
use PhoneBurner\Pinch\Framework\EventSourcing\AggregateRootRepository;
use PhoneBurner\Pinch\Framework\EventSourcing\Attribute\AggregateRootMetadata;
use PhoneBurner\Pinch\Math\Statistics\SummaryStatistics;
use PhoneBurner\Pinch\Memory\Bytes;
use PhoneBurner\Pinch\Time\Timer\StopWatch;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function PhoneBurner\Pinch\Math\int_clamp;

use const PhoneBurner\Pinch\Time\NANOSECONDS_IN_MICROSECOND;

/**
 * @template T of AggregateRoot
 */
#[AsCommand(self::NAME, self::DESCRIPTION)]
final class DebugAggregateRootReconstitutionCommand extends Command
{
    public const string NAME = 'debug:aggregate-root-reconstitution';

    public const string DESCRIPTION = 'Check the reconstitution time of an aggregate root';

    private const array AGGREGATE_ROOTS = [
    ];

    public const string COUNT_AGGREGATE_ROOT_IDS = 'SELECT COUNT(DISTINCT `aggregate_root_id`) FROM `%s`;';
    public const string COUNT_TOTAL_ROWS = 'SELECT COUNT(*) FROM `%s`;';
    public const string SELECT_AGGREGATE_ROOT_SQL = <<<'SQL'
            SELECT `aggregate_root_id` id, MAX(`version`) `version` FROM `%s`
            GROUP BY `aggregate_root_id`
            ORDER BY `version` DESC
            LIMIT %d;
            SQL;
    public const array TABLE_HEADERS = [
        'Aggregate Root ID',
        'Versions',
        'Total Time (μs)',
        'Mean Time (μs)',
    ];
    private const string AGGREGATE_STATISTICS_QUERY = <<<'SQL'
            WITH aggregated_events AS (
                SELECT MAX(version) AS event_count, NTILE(4) OVER (ORDER BY MAX(version)) AS tile
                FROM `%s`
                GROUP BY aggregate_root_id
            )
            SELECT
                MIN(event_count)        AS min,
                MAX(event_count)        AS max,
                AVG(event_count)        AS avg,
                STDDEV_POP(event_count) AS sd,
                MAX(CASE WHEN tile = 1 THEN event_count END) AS q1,
                MAX(CASE WHEN tile = 2 THEN event_count END) AS median,
                MAX(CASE WHEN tile = 3 THEN event_count END) AS q3
            FROM aggregated_events;
            SQL;

    /**
     * @var array<class-string<T>, AggregateRootMetadata>
     */
    private array $metadata;

    /**
     * @param array<class-string<T>> $aggregate_roots
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ContainerInterface $container,
        array $aggregate_roots = self::AGGREGATE_ROOTS,
    ) {
        parent::__construct();
        $this->metadata = \array_map(
            AggregateRootMetadata::lookup(...),
            \array_combine($aggregate_roots, $aggregate_roots),
        );
    }

    protected function configure(): void
    {
        $this->addArgument('aggregate_root', InputArgument::OPTIONAL, <<<'EOL'
            The name of the aggregate root to check
            EOL);

        $this->addOption('max', 'm', InputArgument::OPTIONAL, <<<'EOL'
            The maximum number of aggregate roots to test (default: 10% of total aggregate roots, capped at 1000)
            EOL);
    }

    public function __invoke(InputInterface $input, SymfonyStyle $output, OutputInterface $console_output): int
    {
        $output->title('Debug Aggregate Root Reconstitution');
        $aggregate_root = $this->resolveAggregateRootClass($input, $output);
        $aggregate_root_metadata = $this->metadata[$aggregate_root];
        $output->comment($aggregate_root);

        $aggregate_root_count = $this->calculateEventStoreTableStatistics($output, $aggregate_root_metadata);
        if ($aggregate_root_count === 0) {
            $output->warning('No records found in the aggregate root table. Cannot check reconstitution time.');
            return self::SUCCESS;
        }

        $max = (int)$input->getOption('max');
        $this->calculateAggregateRootReconstitutionTime($output, $aggregate_root_metadata, match ($max) {
            0 => int_clamp($aggregate_root_count * 0.10, 1, 1000),
            -1 => $aggregate_root_count,
            default => \min($max, $aggregate_root_count),
        });

        $output->success('Aggregate Root Reconstitution Checks Complete.');
        return self::SUCCESS;
    }

    private function resolveAggregateRootClass(InputInterface $input, SymfonyStyle $output): string
    {
        $name = (string)$input->getArgument('aggregate_root') ?: $output->choice(
            'Please select an aggregate root',
            \array_keys($this->metadata),
        );

        $map = [];
        foreach ($this->metadata as $class => $metadata) {
            $map[\strtolower($class)] = $class;
            $map[\strtolower(new \ReflectionClass($class)->getShortName())] = $class;
            $map[\strtolower($metadata->table)] = $class;
        }

        return $map[\strtolower((string)$name)] ?? throw new \UnexpectedValueException(\sprintf(
            'Invalid aggregate root class or table name: %s',
            $name,
        ));
    }

    private function calculateEventStoreTableStatistics(
        SymfonyStyle $output,
        AggregateRootMetadata $aggregate_root_metadata,
    ): int {
        $debug = static function (string $message) use ($output): string {
            if ($output->isDebug()) {
                $output->comment(\sprintf("%s\n", $message));
            }
            return $message;
        };

        $progress = new ProgressIndicator($output);

        $operation = 'Calculating Statistics for Table: ' . $aggregate_root_metadata->table;
        $progress->start($operation);

        $aggregate_root_version_count = (int)$this->connection->fetchOne(
            $debug(\sprintf(self::COUNT_TOTAL_ROWS, $aggregate_root_metadata->table)),
        );
        $progress->advance();

        $aggregate_root_id_count = (int)$this->connection->fetchOne(
            $debug(\sprintf(self::COUNT_AGGREGATE_ROOT_IDS, $aggregate_root_metadata->table)),
        );
        $progress->advance();

        $statistics = $this->connection->fetchAssociative(
            $debug(\sprintf(self::AGGREGATE_STATISTICS_QUERY, $aggregate_root_metadata->table)),
        ) ?: throw new \RuntimeException('Failed to fetch statistics for aggregate root events.');
        $statistics = new SummaryStatistics(
            n: $aggregate_root_version_count,
            mean: \round((float)$statistics['avg'], 2),
            sd: \round((float)$statistics['sd'], 2),
            min: $statistics['min'],
            q1: $statistics['q1'],
            median: $statistics['median'],
            q3: $statistics['q3'],
            max: $statistics['max'],
        );
        $progress->advance();

        if ($output->isDecorated()) {
            $progress->finish($operation);
            $output->newLine();
        }

        $output->createTable()
            ->setStyle('box')
            ->setColumnStyle(1, new TableStyle()->setPadType(\STR_PAD_LEFT))
            ->setHeaders([self::pad('Event Store Totals'), self::pad('Value', 10)])
            ->setRows([
                ['Aggregate Root IDs', $aggregate_root_id_count],
                ['Aggregate Root Versions', $aggregate_root_version_count],
            ])
            ->render();

        $output->createTable()
            ->setStyle('box')
            ->setColumnStyle(1, new TableStyle()->setPadType(\STR_PAD_LEFT))
            ->setHeaders([self::pad('Versions Per Aggregate Root'), self::pad('Value', 10)])
            ->setRows(self::formatStatisticsAsTableRows($statistics))
            ->render();
        $output->newLine();

        return $aggregate_root_id_count;
    }

    private function calculateAggregateRootReconstitutionTime(
        SymfonyStyle $output,
        AggregateRootMetadata $aggregate_root_metadata,
        int $aggregate_root_count,
    ): int {
        $debug = static function (string $message) use ($output): string {
            if ($output->isDebug()) {
                $output->comment(\sprintf("%s\n", $message));
            }
            return $message;
        };

        $progress = new ProgressIndicator($output);
        $operation = \sprintf('Reconstituting the %d Aggregate Roots with the Most Versions', $aggregate_root_count);
        $progress->start($operation);

        $repository = $this->container->get($aggregate_root_metadata->repository);
        \assert($repository instanceof AggregateRootRepository);

        $rows = $this->connection->executeQuery($debug(\sprintf(
            self::SELECT_AGGREGATE_ROOT_SQL,
            $aggregate_root_metadata->table,
            $aggregate_root_count,
        )))->fetchAllAssociative();

        $total_stopwatch = StopWatch::start();
        $total_bytes = new Bytes(\memory_get_usage());
        $aggregate_root_reconstitution_times = [];
        $average_event_processing_times = [];
        foreach ($rows as $key => $row) {
            $test_stopwatch = Stopwatch::start();
            $repository->retrieve($row['id']);
            $elapsed = $test_stopwatch->elapsed();
            $rows[$key]['time'] = $elapsed->nanoseconds;
            $average_event_processing_times[] = $elapsed->nanoseconds / $row['version'];
            $aggregate_root_reconstitution_times[] = $elapsed->nanoseconds;
            $progress->advance();
        }
        $total_elapsed = $total_stopwatch->elapsed();
        $total_bytes = new Bytes(\memory_get_usage() - $total_bytes->value);

        if ($output->isDecorated()) {
            $progress->finish($operation);
            $output->newLine();
        }

        // Render the summary statistics for aggregate root reconstitution times
        $statistics = SummaryStatistics::sample($aggregate_root_reconstitution_times);
        self::table(
            $output,
            [self::pad('Aggregate Root Reconstitution Time'), self::pad('Value (μs)', 10)],
            self::convertNanosecondStatisticToMicroseconds($statistics),
        );

        // Render the summary statistics for average event processing times
        $statistics = SummaryStatistics::sample($average_event_processing_times);
        self::table(
            $output,
            [self::pad('Average Reconstitution Time Per Version'), self::pad('Value (μs)', 10)],
            self::convertNanosecondStatisticToMicroseconds($statistics),
        );

        // Render the table with individual aggregate root reconstitution times
        if ($output->isVerbose()) {
            self::table($output, self::TABLE_HEADERS, \array_map(static fn(array $row): array => [
                $row['id'],
                $row['version'],
                \round($row['time'] / NANOSECONDS_IN_MICROSECOND),
                \round($row['time'] / NANOSECONDS_IN_MICROSECOND / $row['version']),
            ], $rows));
        }

        $output->text(\sprintf(
            "Total Time Reconstituting %d Aggregate Roots: %s seconds",
            $aggregate_root_count,
            $total_elapsed->inSeconds(2),
        ));

        $output->text(\sprintf(
            "Total Memory Usage Reconstituting Aggregate Roots: %s (Peak: %s)",
            $total_bytes,
            new Bytes(\memory_get_peak_usage()),
        ));

        return self::SUCCESS;
    }

    private static function table(SymfonyStyle $output, array $headers, array|SummaryStatistics $rows): void
    {
        $style = new TableStyle()->setPadType(\STR_PAD_LEFT);
        $output->createTable()
            ->setStyle('box')
            ->setColumnStyle(1, $style)
            ->setColumnStyle(2, $style)
            ->setColumnStyle(3, $style)
            ->setHeaders($headers)
            ->setRows($rows instanceof SummaryStatistics ? self::formatStatisticsAsTableRows($rows) : $rows)
            ->render();
        $output->newLine();
    }

    private static function pad(string $string, int $length = 50): string
    {
        return \str_pad($string, $length, ' ', \STR_PAD_RIGHT);
    }

    private static function formatStatisticsAsTableRows(SummaryStatistics $statistics): array
    {
        return [
            ['Mean', $statistics->mean],
            ['Standard Deviation', $statistics->sd],
            ['Minimum', $statistics->min],
            ['Q1', $statistics->q1],
            ['Median', $statistics->median],
            ['Q3', $statistics->q3],
            ['Maximum', $statistics->max],
        ];
    }

    private static function convertNanosecondStatisticToMicroseconds(SummaryStatistics $statistics): SummaryStatistics
    {
        return new SummaryStatistics(
            n: $statistics->n,
            mean: \round($statistics->mean / NANOSECONDS_IN_MICROSECOND),
            sd: \round($statistics->sd / NANOSECONDS_IN_MICROSECOND),
            min: \round($statistics->min / NANOSECONDS_IN_MICROSECOND),
            q1: \round($statistics->q1 / NANOSECONDS_IN_MICROSECOND),
            median: \round($statistics->median / NANOSECONDS_IN_MICROSECOND),
            q3: \round($statistics->q3 / NANOSECONDS_IN_MICROSECOND),
            max: \round($statistics->max / NANOSECONDS_IN_MICROSECOND),
        );
    }
}
