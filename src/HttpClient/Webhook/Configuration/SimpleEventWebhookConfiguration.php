<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration;

use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfiguration as WebhookConfigurationContract;
use Psr\Http\Message\UriInterface;

final class SimpleEventWebhookConfiguration implements WebhookConfigurationContract
{
    // phpcs:disable
    public HttpMethod $method {
        get => HttpMethod::Post;
    }
    // phpcs:enable

    /**
     * @param list<class-string> $events
     * @param list<string> $extra_headers
     * @param non-negative-int $connect_timeout_seconds
     * @param non-negative-int $request_timeout_seconds
     * @param non-negative-int $max_retry_attempts
     */
    public function __construct(
        public UriInterface|string $uri,
        public array $events = [],
        public array $extra_headers = [],
        public int $connect_timeout_seconds = WebhookConfigurationContract::DEFAULT_CONNECT_TIMEOUT_SECONDS,
        public int $request_timeout_seconds = WebhookConfigurationContract::DEFAULT_REQUEST_TIMEOUT_SECONDS,
        public int $max_retry_attempts = WebhookConfigurationContract::DEFAULT_MAX_RETRY_ATTEMPTS,
    ) {
        \assert($connect_timeout_seconds >= 0);
        \assert($request_timeout_seconds >= 0);
        \assert($max_retry_attempts >= 0);
    }

    public function shouldTriggerForEvent(string $event_class): bool
    {
        return \in_array($event_class, $this->events, true) || \in_array('*', $this->events, true);
    }

    public function toArray(): array
    {
        return [
            'url' => (string)$this->uri,
            'events' => $this->events,
            'extra_headers' => $this->extra_headers,
            'connect_timeout_seconds' => $this->connect_timeout_seconds,
            'request_timeout_seconds' => $this->request_timeout_seconds,
            'max_retry_attempts' => $this->max_retry_attempts,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uri: $data['url'],
            events: $data['events'] ?? [],
            extra_headers: $data['extra_headers'] ?? [],
            connect_timeout_seconds: $data['connect_timeout_seconds'] ?? WebhookConfigurationContract::DEFAULT_CONNECT_TIMEOUT_SECONDS,
            request_timeout_seconds: $data['request_timeout_seconds'] ?? WebhookConfigurationContract::DEFAULT_REQUEST_TIMEOUT_SECONDS,
            max_retry_attempts: $data['max_retry_attempts'] ?? WebhookConfigurationContract::DEFAULT_MAX_RETRY_ATTEMPTS,
        );
    }
}
