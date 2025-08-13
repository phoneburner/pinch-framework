<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\HttpClient\Webhook;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfiguration;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class MockWebhookDeliveryMessage implements WebhookDeliveryMessage
{
    public readonly UuidInterface $webhook_id;
    public readonly \DateTimeImmutable $timestamp;

    public function __construct(
        public WebhookConfiguration $configuration = new MockWebhookConfiguration(),
        UuidInterface|null $webhook_id = null,
        \DateTimeImmutable|null $timestamp = null,
        public \JsonSerializable|\Stringable|string|array $payload = ['test' => 'data'],
        public int $attempt = 1,
    ) {
        $this->webhook_id = $webhook_id ?? Uuid::uuid4();
        $this->timestamp = $timestamp ?? new \DateTimeImmutable();
    }

    public function withNextAttempt(): self
    {
        return new self(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->payload,
            attempt: $this->attempt + 1,
        );
    }
}
