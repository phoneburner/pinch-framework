<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Notifier\Slack;

use PhoneBurner\Pinch\Time\Interval\TimeInterval;
use Psr\Log\LoggerInterface;

final readonly class NullSlackNotificationClient implements SlackNotificationClient
{
    public function __construct(
        private LoggerInterface $logger,
        public string $channel = '',
    ) {
    }

    #[\Override]
    public function send(SlackNotification $notification, TimeInterval|null $ttl = null): bool
    {
        $this->logger->debug('Slack Message Dispatched', [
            'text' => $notification->message,
            'channel' => $notification->channel ?? $this->channel,
        ]);

        return true;
    }
}
