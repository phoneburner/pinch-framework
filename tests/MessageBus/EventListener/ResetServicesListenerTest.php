<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\MessageBus\EventListener;

use PhoneBurner\Pinch\Framework\MessageBus\EventListener\ResetServicesListener;
use PhoneBurner\Pinch\Framework\MessageBus\LongRunningProcessServiceResetter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Worker;

final class ResetServicesListenerTest extends TestCase
{
    private LongRunningProcessServiceResetter&MockObject $service_resetter;

    private LoggerInterface&MockObject $logger;

    private ResetServicesListener $listener;

    protected function setUp(): void
    {
        $this->service_resetter = $this->createMock(LongRunningProcessServiceResetter::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new ResetServicesListener(
            $this->service_resetter,
            $this->logger,
        );
    }

    #[Test]
    public function doesNotResetServicesWhenWorkerIsIdle(): void
    {
        $event = new WorkerRunningEvent(
            $this->createMock(Worker::class),
            true,
        );

        $this->service_resetter->expects($this->never())
            ->method('reset');

        $this->logger->expects($this->never())
            ->method('debug');

        $this->listener->resetServices($event);
    }

    #[Test]
    public function resetsServicesWhenWorkerIsNotIdle(): void
    {
        $event = new WorkerRunningEvent(
            $this->createMock(Worker::class),
            false,
        );

        $this->service_resetter->expects($this->once())
            ->method('reset');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Resetting Services');

        $this->listener->resetServices($event);
    }

    #[Test]
    public function resetsServicesWhenWorkerStops(): void
    {
        $event = new WorkerStoppedEvent($this->createMock(Worker::class));

        $this->service_resetter->expects($this->once())
            ->method('reset');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Resetting Services');

        $this->listener->resetServicesAtStop($event);
    }
}
