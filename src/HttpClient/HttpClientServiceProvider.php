<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\HttpClient\HttpClient;
use PhoneBurner\Pinch\Component\HttpClient\HttpClientFactory;
use PhoneBurner\Pinch\Component\HttpClient\Psr18ClientWrapper;
use PhoneBurner\Pinch\Framework\HttpClient\Config\HttpClientConfigStruct;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;

/**
 * Service provider for HTTP Client with Guzzle implementation
 *
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class HttpClientServiceProvider implements DeferrableServiceProvider
{
    public static function provides(): array
    {
        return [
            HttpClientFactory::class,
            HttpClient::class,
            ClientInterface::class,
        ];
    }

    public static function bind(): array
    {
        return [
            HttpClient::class => Psr18ClientWrapper::class,
            ClientInterface::class => Psr18ClientWrapper::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(
            HttpClientFactory::class,
            static fn(App $app): HttpClientFactory => new GuzzleHttpClientFactory(
                $app->get(EventDispatcherInterface::class),
            ),
        );

        $app->set(
            HttpClient::class,
            static function (App $app): HttpClient {
                $config = $app->get(HttpClientConfigStruct::class);
                return $app->get(HttpClientFactory::class)->createHttpClient(
                    $config->default_request_timeout_seconds,
                    $config->default_connect_timeout_seconds,
                    ...$config->extra_guzzle_options,
                );
            },
        );
    }
}
