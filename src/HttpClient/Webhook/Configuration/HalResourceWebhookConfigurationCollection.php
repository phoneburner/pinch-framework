<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfigurationCollection;

/**
 * @implements WebhookConfigurationCollection<HalResourceWebhookConfiguration>
 */
final readonly class HalResourceWebhookConfigurationCollection implements WebhookConfigurationCollection
{
    /**
     * @var array<HalResourceWebhookConfiguration>
     */
    public array $webhooks;

    public function __construct(HalResourceWebhookConfiguration ...$webhooks)
    {
        $this->webhooks = $webhooks;
    }

    public function count(): int
    {
        return \count($this->webhooks);
    }

    public function getIterator(): \Generator
    {
        yield from $this->webhooks;
    }

    /**
     * @return array<HalResourceWebhookConfiguration>
     */
    public function forEvent(string $event_class): array
    {
        return \array_filter(
            $this->webhooks,
            static fn(HalResourceWebhookConfiguration $webhook): bool => $webhook->shouldTriggerForEvent($event_class),
        );
    }
}
