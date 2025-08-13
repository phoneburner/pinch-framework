<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\Configuration;

use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfiguration as WebhookConfigurationContract;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class SimpleEventWebhookConfigurationTest extends TestCase
{
    #[Test]
    public function constructorWithDefaultValues(): void
    {
        $uri = 'https://example.com/webhook';
        $configuration = new SimpleEventWebhookConfiguration($uri);

        self::assertSame($uri, $configuration->uri);
        self::assertEmpty($configuration->events);
        self::assertEmpty($configuration->extra_headers);
        self::assertSame(WebhookConfigurationContract::DEFAULT_CONNECT_TIMEOUT_SECONDS, $configuration->connect_timeout_seconds);
        self::assertSame(WebhookConfigurationContract::DEFAULT_REQUEST_TIMEOUT_SECONDS, $configuration->request_timeout_seconds);
        self::assertSame(WebhookConfigurationContract::DEFAULT_MAX_RETRY_ATTEMPTS, $configuration->max_retry_attempts);
    }

    #[Test]
    #[DataProvider('provideValidConstructorParameters')]
    public function constructorWithCustomValues(
        UriInterface|string $uri,
        array $events,
        array $extra_headers,
        int $connect_timeout_seconds,
        int $request_timeout_seconds,
        int $max_retry_attempts,
    ): void {
        $configuration = new SimpleEventWebhookConfiguration(
            uri: $uri,
            events: $events, // @phpstan-ignore argument.type
            extra_headers: $extra_headers, // @phpstan-ignore argument.type
            connect_timeout_seconds: $connect_timeout_seconds, // @phpstan-ignore argument.type
            request_timeout_seconds: $request_timeout_seconds, // @phpstan-ignore argument.type
            max_retry_attempts: $max_retry_attempts, // @phpstan-ignore argument.type
        );

        self::assertSame($uri, $configuration->uri);
        self::assertSame($events, $configuration->events);
        self::assertSame($extra_headers, $configuration->extra_headers);
        self::assertSame($connect_timeout_seconds, $configuration->connect_timeout_seconds);
        self::assertSame($request_timeout_seconds, $configuration->request_timeout_seconds);
        self::assertSame($max_retry_attempts, $configuration->max_retry_attempts);
    }

    public static function provideValidConstructorParameters(): \Generator
    {
        $mock_uri = new class implements UriInterface {
            public function __toString(): string
            {
                return 'https://example.com/webhook';
            }

            public function getScheme(): string
            {
                return 'https';
            }

            public function getAuthority(): string
            {
                return 'example.com';
            }

            public function getUserInfo(): string
            {
                return '';
            }

            public function getHost(): string
            {
                return 'example.com';
            }

            public function getPort(): int|null
            {
                return null;
            }

            public function getPath(): string
            {
                return '/webhook';
            }

            public function getQuery(): string
            {
                return '';
            }

            public function getFragment(): string
            {
                return '';
            }

            public function withScheme(string $scheme): UriInterface
            {
                return $this;
            }

            public function withUserInfo(string $user, string|null $password = null): UriInterface
            {
                return $this;
            }

            public function withHost(string $host): UriInterface
            {
                return $this;
            }

            public function withPort(int|null $port): UriInterface
            {
                return $this;
            }

            public function withPath(string $path): UriInterface
            {
                return $this;
            }

            public function withQuery(string $query): UriInterface
            {
                return $this;
            }

            public function withFragment(string $fragment): UriInterface
            {
                return $this;
            }
        };

        yield 'string uri with basic config' => [
            'https://example.com/webhook',
            ['App\\Events\\UserCreated'],
            ['X-Custom-Header' => 'value'],
            10,
            30,
            3,
        ];

        yield 'uri interface with multiple events' => [
            $mock_uri,
            ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated'], // @phpstan-ignore argument.type
            ['Authorization' => 'Bearer token', 'Content-Type' => 'application/json'], // @phpstan-ignore argument.type
            15,
            45,
            7,
        ];

        yield 'wildcard events' => [
            'https://webhook.example.com/events',
            ['*'],
            [],
            0,
            0,
            0,
        ];

        yield 'empty events array' => [
            'https://example.com/webhook',
            [],
            [],
            1,
            1,
            1,
        ];
    }

    #[Test]
    public function methodPropertyAlwaysReturnsPost(): void
    {
        $configuration = new SimpleEventWebhookConfiguration('https://example.com/webhook');

        self::assertSame(HttpMethod::Post, $configuration->method);
    }

    #[Test]
    #[DataProvider('provideShouldTriggerForEventTestCases')]
    public function shouldTriggerForEvent(array $events, string $event_class, bool $expected): void
    {
        $configuration = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook',
            events: $events, // @phpstan-ignore argument.type
        );

        $result = $configuration->shouldTriggerForEvent($event_class);

        self::assertSame($expected, $result);
    }

    public static function provideShouldTriggerForEventTestCases(): \Generator
    {
        yield 'exact match returns true' => [
            ['App\\Events\\UserCreated'],
            'App\\Events\\UserCreated',
            true,
        ];

        yield 'no match returns false' => [
            ['App\\Events\\UserCreated'],
            'App\\Events\\UserUpdated',
            false,
        ];

        yield 'wildcard matches any event' => [
            ['*'],
            'App\\Events\\SomeRandomEvent',
            true,
        ];

        yield 'wildcard with specific events matches both' => [
            ['App\\Events\\UserCreated', '*'],
            'App\\Events\\UserDeleted',
            true,
        ];

        yield 'multiple events with match' => [
            ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated', 'App\\Events\\UserDeleted'], // @phpstan-ignore argument.type
            'App\\Events\\UserUpdated',
            true,
        ];

        yield 'multiple events without match' => [
            ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated'], // @phpstan-ignore argument.type
            'App\\Events\\UserDeleted',
            false,
        ];

        yield 'empty events array returns false' => [
            [],
            'App\\Events\\UserCreated',
            false,
        ];

        yield 'case sensitive matching' => [
            ['App\\Events\\UserCreated'],
            'app\\events\\usercreated',
            false,
        ];
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $uri = 'https://example.com/webhook';
        $events = ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated']; // @phpstan-ignore argument.type
        $extra_headers = ['Authorization' => 'Bearer token']; // @phpstan-ignore argument.type
        $connect_timeout = 15;
        $request_timeout = 30;
        $max_retries = 5;

        $configuration = new SimpleEventWebhookConfiguration(
            uri: $uri,
            events: $events, // @phpstan-ignore argument.type
            extra_headers: $extra_headers, // @phpstan-ignore argument.type
            connect_timeout_seconds: $connect_timeout,
            request_timeout_seconds: $request_timeout,
            max_retry_attempts: $max_retries,
        );

        $array = $configuration->toArray();

        $expected = [
            'url' => $uri,
            'events' => $events,
            'extra_headers' => $extra_headers,
            'connect_timeout_seconds' => $connect_timeout,
            'request_timeout_seconds' => $request_timeout,
            'max_retry_attempts' => $max_retries,
        ];

        self::assertSame($expected, $array);
    }

    #[Test]
    public function toArrayWithUriInterfaceConvertsToString(): void
    {
        $uri_string = 'https://example.com/webhook';
        $mock_uri = new readonly class ($uri_string) implements UriInterface {
            public function __construct(private string $uri)
            {
            }

            public function __toString(): string
            {
                return $this->uri;
            }

            public function getScheme(): string
            {
                return 'https';
            }

            public function getAuthority(): string
            {
                return 'example.com';
            }

            public function getUserInfo(): string
            {
                return '';
            }

            public function getHost(): string
            {
                return 'example.com';
            }

            public function getPort(): int|null
            {
                return null;
            }

            public function getPath(): string
            {
                return '/webhook';
            }

            public function getQuery(): string
            {
                return '';
            }

            public function getFragment(): string
            {
                return '';
            }

            public function withScheme(string $scheme): UriInterface
            {
                return $this;
            }

            public function withUserInfo(string $user, string|null $password = null): UriInterface
            {
                return $this;
            }

            public function withHost(string $host): UriInterface
            {
                return $this;
            }

            public function withPort(int|null $port): UriInterface
            {
                return $this;
            }

            public function withPath(string $path): UriInterface
            {
                return $this;
            }

            public function withQuery(string $query): UriInterface
            {
                return $this;
            }

            public function withFragment(string $fragment): UriInterface
            {
                return $this;
            }
        };

        $configuration = new SimpleEventWebhookConfiguration(uri: $mock_uri);
        $array = $configuration->toArray();

        self::assertSame($uri_string, $array['url']);
    }

    #[Test]
    #[DataProvider('provideFromArrayTestCases')]
    public function fromArrayCreatesCorrectInstance(array $data, array $expected_values): void
    {
        $configuration = SimpleEventWebhookConfiguration::fromArray($data);

        self::assertSame($expected_values['url'], $configuration->uri);
        self::assertSame($expected_values['events'], $configuration->events);
        self::assertSame($expected_values['extra_headers'], $configuration->extra_headers);
        self::assertSame($expected_values['connect_timeout_seconds'], $configuration->connect_timeout_seconds);
        self::assertSame($expected_values['request_timeout_seconds'], $configuration->request_timeout_seconds);
        self::assertSame($expected_values['max_retry_attempts'], $configuration->max_retry_attempts);
    }

    public static function provideFromArrayTestCases(): \Generator
    {
        yield 'complete data array' => [
            [
                'url' => 'https://example.com/webhook',
                'events' => ['App\\Events\\UserCreated'],
                'extra_headers' => ['Authorization' => 'Bearer token'],
                'connect_timeout_seconds' => 20,
                'request_timeout_seconds' => 40,
                'max_retry_attempts' => 3,
            ],
            [
                'url' => 'https://example.com/webhook',
                'events' => ['App\\Events\\UserCreated'],
                'extra_headers' => ['Authorization' => 'Bearer token'],
                'connect_timeout_seconds' => 20,
                'request_timeout_seconds' => 40,
                'max_retry_attempts' => 3,
            ],
        ];

        yield 'minimal data with defaults' => [
            [
                'url' => 'https://example.com/webhook',
            ],
            [
                'url' => 'https://example.com/webhook',
                'events' => [],
                'extra_headers' => [],
                'connect_timeout_seconds' => WebhookConfigurationContract::DEFAULT_CONNECT_TIMEOUT_SECONDS,
                'request_timeout_seconds' => WebhookConfigurationContract::DEFAULT_REQUEST_TIMEOUT_SECONDS,
                'max_retry_attempts' => WebhookConfigurationContract::DEFAULT_MAX_RETRY_ATTEMPTS,
            ],
        ];

        yield 'partial data with some defaults' => [
            [
                'url' => 'https://api.example.com/webhooks',
                'events' => ['*'],
                'connect_timeout_seconds' => 10,
            ],
            [
                'url' => 'https://api.example.com/webhooks',
                'events' => ['*'],
                'extra_headers' => [],
                'connect_timeout_seconds' => 10,
                'request_timeout_seconds' => WebhookConfigurationContract::DEFAULT_REQUEST_TIMEOUT_SECONDS,
                'max_retry_attempts' => WebhookConfigurationContract::DEFAULT_MAX_RETRY_ATTEMPTS,
            ],
        ];
    }

    #[Test]
    public function toArrayAndFromArrayAreSymmetric(): void
    {
        $original_configuration = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook',
            events: ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated'], // @phpstan-ignore argument.type
            extra_headers: ['Authorization' => 'Bearer token', 'X-Custom' => 'value'], // @phpstan-ignore argument.type
            connect_timeout_seconds: 25,
            request_timeout_seconds: 60,
            max_retry_attempts: 7,
        );

        $array = $original_configuration->toArray();
        $restored_configuration = SimpleEventWebhookConfiguration::fromArray($array);

        // Compare all properties
        self::assertSame($original_configuration->uri, $restored_configuration->uri);
        self::assertSame($original_configuration->events, $restored_configuration->events);
        self::assertSame($original_configuration->extra_headers, $restored_configuration->extra_headers);
        self::assertSame($original_configuration->connect_timeout_seconds, $restored_configuration->connect_timeout_seconds);
        self::assertSame($original_configuration->request_timeout_seconds, $restored_configuration->request_timeout_seconds);
        self::assertSame($original_configuration->max_retry_attempts, $restored_configuration->max_retry_attempts);
        self::assertSame($original_configuration->method, $restored_configuration->method);
    }

    #[Test]
    #[DataProvider('provideInvalidTimeoutValues')]
    public function constructorAssertsNonNegativeTimeoutValues(
        int $connect_timeout,
        int $request_timeout,
        int $max_retries,
    ): void {
        $this->expectException(\AssertionError::class);

        new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook',
            connect_timeout_seconds: $connect_timeout, // @phpstan-ignore argument.type
            request_timeout_seconds: $request_timeout, // @phpstan-ignore argument.type
            max_retry_attempts: $max_retries, // @phpstan-ignore argument.type
        );
    }

    public static function provideInvalidTimeoutValues(): \Generator
    {
        yield 'negative connect timeout' => [
            -1,
            10,
            5,
        ];

        yield 'negative request timeout' => [
            5,
            -1,
            5,
        ];

        yield 'negative max retries' => [
            5,
            10,
            -1,
        ];

        yield 'all negative values' => [
            -5,
            -10,
            -3,
        ];
    }

    #[Test]
    public function configurationsWithSameDataAreEquivalent(): void
    {
        $data = [
            'url' => 'https://example.com/webhook',
            'events' => ['App\\Events\\UserCreated'],
            'extra_headers' => ['Authorization' => 'Bearer token'],
            'connect_timeout_seconds' => 15,
            'request_timeout_seconds' => 30,
            'max_retry_attempts' => 5,
        ];

        $config1 = SimpleEventWebhookConfiguration::fromArray($data);
        $config2 = SimpleEventWebhookConfiguration::fromArray($data);

        // They should have identical properties
        self::assertSame($config1->uri, $config2->uri);
        self::assertSame($config1->events, $config2->events);
        self::assertSame($config1->extra_headers, $config2->extra_headers);
        self::assertSame($config1->connect_timeout_seconds, $config2->connect_timeout_seconds);
        self::assertSame($config1->request_timeout_seconds, $config2->request_timeout_seconds);
        self::assertSame($config1->max_retry_attempts, $config2->max_retry_attempts);
        self::assertSame($config1->method, $config2->method);

        // And produce identical arrays
        self::assertSame($config1->toArray(), $config2->toArray());
    }
}
