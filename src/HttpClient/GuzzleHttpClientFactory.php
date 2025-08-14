<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PhoneBurner\Pinch\Component\HttpClient\HttpClientFactory;
use PhoneBurner\Pinch\Component\HttpClient\Psr18ClientWrapper;
use Psr\EventDispatcher\EventDispatcherInterface;

class GuzzleHttpClientFactory implements HttpClientFactory
{
    public function __construct(
        private readonly EventDispatcherInterface $event_dispatcher,
    ) {
    }

    /**
     * Note: when used as a PSR-18 client, Guzzle will always override these options:
     * - RequestOptions::SYNCHRONOUS => true
     * - RequestOptions::ALLOW_REDIRECTS => false
     * - RequestOptions::HTTP_ERRORS => false
     *
     * @param float $request_timeout_seconds // indefinite timeout by default
     * @param float $connect_timeout_seconds // indefinite timeout by default
     * @param array<string, mixed> $extra_guzzle_options
     */
    public function createHttpClient(
        float $request_timeout_seconds = 0.0,
        float $connect_timeout_seconds = 0.0,
        bool $enable_ssl_verification = true,
        array $extra_guzzle_options = [],
    ): Psr18ClientWrapper {
        return new Psr18ClientWrapper(
            new Client([
                RequestOptions::TIMEOUT => $request_timeout_seconds,
                RequestOptions::CONNECT_TIMEOUT => $connect_timeout_seconds,
                ...$extra_guzzle_options,
            ]),
            $this->event_dispatcher,
        );
    }
}
