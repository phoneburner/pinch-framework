<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventDispatcher\Command;

use PhoneBurner\Pinch\Framework\EventDispatcher\EventListener\LazyListener;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function PhoneBurner\Pinch\Array\array_wrap;

#[AsCommand(self::NAME, self::DESCRIPTION)]
class DebugEventListenersCommand extends Command
{
    public const string NAME = 'debug:event-dispatcher';

    public const string DESCRIPTION = 'List the event listeners defined in "includes/events.php"';

    public function __construct(private readonly EventDispatcherInterface $event_dispatcher)
    {
        parent::__construct(self::NAME);
        $this->setAliases(['event-dispatcher:list']);
        $this->setDescription(self::DESCRIPTION);
        $this->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'How to display the output, flat test list or table', 'table');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Registered Events and Listeners</info>');

        if ($input->getOption('format') === 'table') {
            $table = new Table($output);
            $table->setHeaders(['Event', 'Listeners']);
            $separator_flag = true;
            foreach ($this->event_dispatcher->getListeners() as $event => $listeners) {
                if ($separator_flag) {
                    $table->addRow(new TableSeparator());
                    $separator_flag = false;
                }

                $table->addRow([
                    \sprintf('<comment>%s</comment>', $event),
                    \implode(\PHP_EOL, \array_map(self::listener(...), array_wrap($listeners))),
                ]);
            }

            $table->render();
            return Command::SUCCESS;
        }

        foreach ($this->event_dispatcher->getListeners() as $event => $listeners) {
            foreach (array_wrap($listeners) as $listener) {
                $output->writeln(\sprintf("<comment>%s</comment>:  %s", $event, self::listener($listener)));
            }
        }
        return Command::SUCCESS;
    }

    private static function listener(object $listener): string
    {
        return match (true) {
            $listener instanceof \Closure => (static function (\Closure $closure): string {
                $reflection = new \ReflectionFunction($closure);
                return \ltrim($reflection->getClosureCalledClass()?->getName() . '::' . $reflection->getName() . '()', ':');
            })($listener),
            ! $listener instanceof LazyListener => $listener::class,
            (bool)$listener->listener_method => $listener->listener_class . '::' . $listener->listener_method,
            default => $listener->listener_class,
        };
    }
}
