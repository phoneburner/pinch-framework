<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\EventListener;

use PhoneBurner\Pinch\Framework\MessageBus\LongRunningProcessServiceResetter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener as SymfonyResetServicesListener;

class ResetServicesListener extends SymfonyResetServicesListener
{
    public function __construct(
        private readonly LongRunningProcessServiceResetter $service_resetter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function resetServices(WorkerRunningEvent $event): void
    {
        if (! $event->isWorkerIdle()) {
            $this->logger->debug('Resetting Services');
            $this->service_resetter->reset();
        }
    }

    #[\Override]
    public function resetServicesAtStop(WorkerStoppedEvent $event): void
    {
        $this->logger->debug('Resetting Services');
        $this->service_resetter->reset();
    }
}
