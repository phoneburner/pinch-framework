<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\Message;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage as WebhookDeliveryMessageContract;
use PhoneBurner\Pinch\Framework\Http\Api\HalResource;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;
use Ramsey\Uuid\UuidInterface;

final class HalResourceWebhookDeliveryMessage implements WebhookDeliveryMessageContract
{
    public function __construct(
        public SimpleEventWebhookConfiguration $configuration,
        public UuidInterface $webhook_id,
        public \DateTimeImmutable $timestamp,
        public HalResource $payload,
        public int $attempt = 1,
    ) {
    }

    public function withNextAttempt(): self
    {
        return new self(
            $this->configuration,
            $this->webhook_id,
            $this->timestamp,
            $this->payload,
            $this->attempt + 1,
        );
    }
}
