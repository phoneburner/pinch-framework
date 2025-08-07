<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console\Configurator;

use PhoneBurner\Pinch\Component\PhoneNumber\InputPhoneNumber;
use PhoneBurner\Pinch\Framework\Console\Configurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * A standardized way to declare a command argument for a list of phone numbers
 * or a CSV file containing phone numbers. The class both configures the command
 * and provides an iterator to yield `InputPhoneNumber` instances from the resolved
 * input or CSV file.
 *
 * @implements \IteratorAggregate<InputPhoneNumber>
 */
class PhoneNumberArgumentIterator implements \IteratorAggregate, Configurator
{
    public const int CSV_FLAGS = 0
        | \SplFileObject::READ_CSV
        | \SplFileObject::READ_AHEAD
        | \SplFileObject::SKIP_EMPTY
        | \SplFileObject::DROP_NEW_LINE;

    public const string LIST_ARG = 'phone_numbers';

    public const string CSV_FILE_OPT = 'file';

    public const string CSV_SKIP_ROWS_OPT = 'skip-rows';

    public const string CSV_COLUMN_OPT = 'column';

    public const int CSV_SKIP_ROWS_DEFAULT = 1;

    public const int CSV_COLUMN_DEFAULT = 0;

    public function __construct(private readonly InputInterface $input)
    {
    }

    #[\Override]
    public function getIterator(): \Iterator
    {
        $phone_numbers = (array)$this->input->getArgument(self::LIST_ARG);
        $csv_file = (string)$this->input->getOption(self::CSV_FILE_OPT);
        if (($csv_file && $phone_numbers) || (! $csv_file && ! $phone_numbers)) {
            throw new \RuntimeException('Must specify either a CSV file or list of phone number arguments');
        }

        yield from \array_map(InputPhoneNumber::make(...), $phone_numbers) ?: $this->fromCsvFile(
            new \SplFileInfo($csv_file),
            (int)$this->input->getOption(self::CSV_SKIP_ROWS_OPT),
            (int)$this->input->getOption(self::CSV_COLUMN_OPT),
        );
    }

    public function fromCsvFile(\SplFileInfo $csv_file, int $skip_rows = 1, int $column = 0): \Generator
    {
        if (! $csv_file->isFile() || ! $csv_file->isReadable()) {
            throw new \RuntimeException('CSV file does not exist or is not readable');
        }

        $csv_file = $csv_file->openFile('rb');
        $csv_file->setFlags(self::CSV_FLAGS);
        foreach (new \LimitIterator($csv_file, $skip_rows) as $row) {
            yield new InputPhoneNumber($row[$column]);
        }
    }

    public static function configure(Command $command): Command
    {
        $command->addArgument(
            name: self::LIST_ARG,
            mode: InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            description: 'The phone numbers to update in E.164 format (e.g. +13145551234)',
        );

        $command->addOption(
            name: self::CSV_FILE_OPT,
            shortcut: 'f',
            mode: InputOption::VALUE_REQUIRED,
            description: 'The path to a CSV file containing the phone numbers to update in the first column',
        );

        $command->addOption(
            name: self::CSV_SKIP_ROWS_OPT,
            mode: InputOption::VALUE_REQUIRED,
            description: 'When importing from a CSV file, the integer number of header rows to skip',
            default: self::CSV_SKIP_ROWS_DEFAULT,
        );

        $command->addOption(
            name: self::CSV_COLUMN_OPT,
            mode: InputOption::VALUE_REQUIRED,
            description: 'When importing from a CSV file, the integer offset of the column with the phone numbers',
            default: self::CSV_COLUMN_DEFAULT,
        );

        return $command;
    }
}
