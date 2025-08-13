<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration;

use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfiguration as WebhookConfigurationContract;
use Psr\Http\Message\UriInterface;

final class HalResourceWebhookConfiguration implements WebhookConfigurationContract
{
    // phpcs:disable
    public HttpMethod $method {
        get => HttpMethod::Post;
    }
    // phpcs:enable

    // phpcs:disable
    public int $connect_timeout_seconds {
        get => WebhookConfigurationContract::DEFAULT_CONNECT_TIMEOUT_SECONDS;
    }
    // phpcs:enable

    // phpcs:disable
    public int $request_timeout_seconds {
        get => $this->timeout_seconds;
    }
    // phpcs:enable

    /**
     * @param list<class-string> $events
     * @param non-negative-int $timeout_seconds
     * @param non-negative-int $max_retry_attempts
     * @param list<string> $extra_headers
     */
    public function __construct(
        public UriInterface|string $uri,
        public array $events = [],
        public int $timeout_seconds = 30,
        public int $max_retry_attempts = 3,
        public array $extra_headers = [],
    ) {
        \assert($timeout_seconds >= 0);
        \assert($max_retry_attempts >= 0);
    }

    public function shouldTriggerForEvent(string $event_class): bool
    {
        return \in_array($event_class, $this->events, true) || \in_array('*', $this->events, true);
    }

    public function toArray(): array
    {
        return [
            'uri' => (string)$this->uri,
            'events' => $this->events,
            'timeout_seconds' => $this->timeout_seconds,
            'max_retry_attempts' => $this->max_retry_attempts,
            'extra_headers' => $this->extra_headers,
        ];
    }

    public static function fromArray(array $data): self
    {
        if (! isset($data['uri'])) {
            throw new \InvalidArgumentException('URI is required');
        }

        return new self(
            uri: $data['uri'],
            events: $data['events'] ?? [],
            timeout_seconds: $data['timeout_seconds'] ?? 30,
            max_retry_attempts: $data['max_retry_attempts'] ?? 3,
            extra_headers: $data['extra_headers'] ?? [],
        );
    }
}
