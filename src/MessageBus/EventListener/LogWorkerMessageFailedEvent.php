<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class LogWorkerMessageFailedEvent
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $this->logger->critical('Message Handling Failure: ' . $event->getThrowable(), [
            'message' => $event->getEnvelope()->getMessage()::class,
            'exception' => $event->getThrowable(),
        ]);
    }
}
