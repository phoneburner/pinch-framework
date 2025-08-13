<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console;

use PhoneBurner\Pinch\Component\App\Event\KernelExecutionCompleted;
use PhoneBurner\Pinch\Component\App\Event\KernelExecutionStarted;
use PhoneBurner\Pinch\Component\App\Kernel;
use Psr\EventDispatcher\EventDispatcherInterface;

class CliKernel implements Kernel
{
    public function __construct(
        private readonly ConsoleApplication $application,
        private readonly EventDispatcherInterface $event_dispatcher,
    ) {
    }

    #[\Override]
    public function run(): never
    {
        $this->event_dispatcher->dispatch(new KernelExecutionStarted($this));
        try {
            $exit_code = $this->application->run();
        } finally {
            $this->event_dispatcher->dispatch(new KernelExecutionCompleted($this));
        }

        /** @phpstan-ignore disallowed.exit */
        exit($exit_code);
    }
}
