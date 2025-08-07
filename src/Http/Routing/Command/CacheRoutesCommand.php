<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command;

use PhoneBurner\Pinch\Component\Configuration\Configuration;
use PhoneBurner\Pinch\Framework\Http\Config\RoutingConfigStruct;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRouter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(self::NAME, self::DESCRIPTION)]
class CacheRoutesCommand extends Command
{
    public const string NAME = 'routing:cache';

    public const string DESCRIPTION = 'Generate the cached routes file';

    public function __construct(
        private readonly Configuration $config,
        private readonly FastRouter $router,
    ) {
        parent::__construct(self::NAME);
        $this->setDescription(self::DESCRIPTION);
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Only clear the routes cache file, without regenerating it');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routing_config = $this->config->get('http.routing');
        if (! $routing_config instanceof RoutingConfigStruct) {
            $output->writeln('<error>Routing configuration is not properly set up!</error>');
            $output->writeln('Please check your configuration files.');
            return self::FAILURE;
        }

        if (! $routing_config->enable_cache) {
            $output->writeln('<comment>Route caching is disabled!</comment>');
            $output->writeln('Set the PINCH_ENABLE_ROUTE_CACHE environment variable to `true` enable it');
            return self::SUCCESS;
        }

        $cache_file = $routing_config->cache_path ?: RoutingConfigStruct::DEFAULT_CACHE_PATH;
        if ($input->getOption('clear') || \file_exists($cache_file)) {
            $output->write("<comment>Clearing Existing Route Cache File:</comment> ");

            if (! \file_exists($cache_file)) {
                $output->writeln("N/A");
            } elseif (\unlink($cache_file)) {
                $output->writeln("<info>OK</info>");
            } else {
                $output->writeln("<error>FAIL</error>");
                return self::FAILURE;
            }
        }

        if ($input->getOption('clear')) {
            return self::SUCCESS;
        }

        $output->write("<comment>Generating Route Cache File:</comment> ");

        \assert(! \file_exists($cache_file));

        $this->router->dispatcher();

        // Verify that we created a valid PHP-parsable file by trying to include it
        // By catching on `\Throwable`, we'll catch any both "file not exists" E_ERROR
        // and "file not parsable" \E_PARSE_ERROR errors.
        try {
            require $cache_file;
            $output->writeln("<info>OK</info>");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(\sprintf('<error>FAIL (%s)</error>', $e->getMessage()));
            return self::FAILURE;
        }
    }
}
