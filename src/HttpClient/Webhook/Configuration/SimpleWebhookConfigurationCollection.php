<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfigurationCollection;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;

/**
 * @implements WebhookConfigurationCollection<SimpleEventWebhookConfiguration>
 */
final readonly class SimpleWebhookConfigurationCollection implements WebhookConfigurationCollection
{
    /**
     * @var array<SimpleEventWebhookConfiguration>
     */
    public array $webhooks;

    public function __construct(SimpleEventWebhookConfiguration ...$webhooks)
    {
        $this->webhooks = $webhooks;
    }

    /**
     * @param class-string $event_class
     * @return array<SimpleEventWebhookConfiguration>
     */
    public function getWebhooksForEvent(string $event_class): array
    {
        return $this->webhooks ? \array_filter(
            $this->webhooks,
            static fn(SimpleEventWebhookConfiguration $webhook): bool => $webhook->shouldTriggerForEvent($event_class),
        ) : [];
    }

    public function count(): int
    {
        return \count($this->webhooks);
    }

    public function getIterator(): \Generator
    {
        yield from $this->webhooks;
    }
}
