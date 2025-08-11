<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Notifier\Slack;

use Maknz\Slack\Client;
use Maknz\Slack\Message;
use PhoneBurner\Pinch\Component\Cache\CacheKey;
use PhoneBurner\Pinch\Component\Cache\Lock\LockFactory;
use PhoneBurner\Pinch\Time\Interval\TimeInterval;
use Psr\Log\LoggerInterface;

class SlackWebhookNotificationClient implements SlackNotificationClient
{
    public function __construct(
        private readonly Client $client,
        private readonly LockFactory $lock_factory,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function send(SlackNotification $notification, TimeInterval|null $ttl = null): bool
    {
        $message = $this->client->createMessage()->setText($notification->message);
        if ($notification->channel !== null) {
            $message->setChannel($notification->channel);
        }

        // If the message is locked, we will not send it, preventing spamming the channel
        if ($ttl instanceof TimeInterval && $this->messageIsLocked($message, $ttl)) {
            $this->logger->debug('Slack Message Not Dispatched Due to Locking Policy', [
                'text' => $message->getText(),
                'channel' => $message->getChannel(),
            ]);
            return false;
        }

        try {
            $this->client->send($message);
            $this->logger->debug('Slack Message Dispatched', [
                'text' => $message->getText(),
                'channel' => $message->getChannel(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Slack Message Failed to Dispatch', [
                'text' => $message->getText(),
                'channel' => $message->getChannel(),
                'exception' => $e,
            ]);
        }

        return false;
    }

    /**
     * Returns true if the message is already locked, false otherwise
     */
    private function messageIsLocked(Message $message, TimeInterval $ttl): bool
    {
        $key = CacheKey::make($message->getChannel(), $message->getText());
        return ! $this->lock_factory->make($key, $ttl, false)->acquire();
    }
}
