<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\Message;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage as WebhookDeliveryMessageContract;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;
use PhoneBurner\Pinch\Time\Standards\Rfc3339;
use Ramsey\Uuid\UuidInterface;

final class SimpleEventWebhookDeliveryMessage implements WebhookDeliveryMessageContract
{
    public function __construct(
        public SimpleEventWebhookConfiguration $configuration,
        public UuidInterface $webhook_id,
        public \DateTimeImmutable $timestamp,
        public string $event_class,
        public array $event_payload,
        public int $attempt = 1,
    ) {
    }

    public function withNextAttempt(): self
    {
        return new self(
            $this->configuration,
            $this->webhook_id,
            $this->timestamp,
            $this->event_class,
            $this->event_payload,
            $this->attempt + 1,
        );
    }

    // phpcs:disable
    public array $payload {
        get => [
            'webhook_id' => $this->webhook_id,
            'timestamp' => $this->timestamp->format(Rfc3339::DATETIME),
            'event_class' => $this->event_class,
            'event_payload' => $this->event_payload,
        ];
    }
    // phpcs:enable
}
