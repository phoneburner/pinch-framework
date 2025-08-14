<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfiguration;

final readonly class HttpClientConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param float $default_connect_timeout_seconds indefinite by default
     * @param float $default_request_timeout_seconds indefinite by default
     * @param array<string, mixed> $extra_guzzle_options Guzzle HTTP client configuration options
     * @param list<WebhookConfiguration> $webhooks
     */
    public function __construct(
        public float $default_connect_timeout_seconds = 0.0,
        public float $default_request_timeout_seconds = 0.0,
        public array $extra_guzzle_options = [],
        public array $webhooks = [],
    ) {
    }
}
