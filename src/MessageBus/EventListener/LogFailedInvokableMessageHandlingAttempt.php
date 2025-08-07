<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\EventListener;

use PhoneBurner\Pinch\Component\MessageBus\Event\InvokableMessageHandlingFailed;
use Psr\Log\LoggerInterface;

class LogFailedInvokableMessageHandlingAttempt
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(InvokableMessageHandlingFailed $event): void
    {
        try {
            $serialized = \serialize($event->message);
        } catch (\Throwable) {
            $serialized = null;
        }

        $this->logger->error('Invokable Message Handling Failed with Error: ' . $event->exception?->getMessage(), [
            'event' => $event->message::class,
            'message' => $serialized,
            'exception' => $event->exception,
        ]);
    }
}
