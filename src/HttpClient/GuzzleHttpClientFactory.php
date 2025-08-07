<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PhoneBurner\Pinch\Component\HttpClient\HttpClientFactory;
use PhoneBurner\Pinch\Component\HttpClient\HttpClientWrapper;
use Psr\EventDispatcher\EventDispatcherInterface;

class GuzzleHttpClientFactory implements HttpClientFactory
{
    public function __construct(
        private readonly EventDispatcherInterface $event_dispatcher,
    ) {
    }

    /**
     * @param float $request_timeout_seconds // indefinite timeout by default
     * @param float $connect_timeout_seconds // indefinite timeout by default
     * @param bool $enable_ssl_verification // don't disable this unless you are a complete idiot.
     */
    public function make(
        float $request_timeout_seconds = 0.0,
        float $connect_timeout_seconds = 0.0,
        bool $enable_ssl_verification = true,
    ): HttpClientWrapper {
        return new HttpClientWrapper(
            new Client([
                RequestOptions::HTTP_ERRORS => false, // Handle errors manually, as per PSR-18
                RequestOptions::TIMEOUT => $request_timeout_seconds, // Total timeout for the request
                RequestOptions::CONNECT_TIMEOUT => $connect_timeout_seconds,
                RequestOptions::VERIFY => $enable_ssl_verification,
            ]),
            $this->event_dispatcher,
        );
    }
}
