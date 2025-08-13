<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\Configuration;

use GuzzleHttp\Psr7\Uri;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\HalResourceWebhookConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

#[CoversClass(HalResourceWebhookConfiguration::class)]
final class HalResourceWebhookConfigurationTest extends TestCase
{
    #[Test]
    public function constructorCreatesConfigurationWithDefaults(): void
    {
        $uri = new Uri('https://webhook.example.com/notify');
        $configuration = new HalResourceWebhookConfiguration($uri);

        self::assertSame($uri, $configuration->uri);
        self::assertEmpty($configuration->events);
        self::assertSame(30, $configuration->timeout_seconds);
        self::assertSame(3, $configuration->max_retry_attempts);
        self::assertEmpty($configuration->extra_headers);
    }

    #[Test]
    public function constructorAcceptsAllParameters(): void
    {
        $uri = new Uri('https://api.example.com/webhooks');
        $events = ['UserCreated', 'OrderCompleted'];
        $timeout = 45;
        $max_retries = 5;
        $extra_headers = ['Authorization' => 'Bearer token123', 'X-Source' => 'app'];

        $configuration = new HalResourceWebhookConfiguration(
            $uri,
            $events, // @phpstan-ignore argument.type
            $timeout,
            $max_retries,
            $extra_headers, // @phpstan-ignore argument.type
        );

        self::assertSame($uri, $configuration->uri);
        self::assertSame($events, $configuration->events);
        self::assertSame($timeout, $configuration->timeout_seconds);
        self::assertSame($max_retries, $configuration->max_retry_attempts);
        self::assertSame($extra_headers, $configuration->extra_headers);
    }

    #[Test]
    public function constructorThrowsExceptionForNegativeTimeout(): void
    {
        $this->expectException(\AssertionError::class);

        new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            [],
            -1, // @phpstan-ignore argument.type
        );
    }

    #[Test]
    public function constructorThrowsExceptionForNegativeMaxRetryAttempts(): void
    {
        $this->expectException(\AssertionError::class);

        new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            [],
            30,
            -1, // @phpstan-ignore argument.type
        );
    }

    #[Test]
    public function methodPropertyAlwaysReturnsPost(): void
    {
        $configuration = new HalResourceWebhookConfiguration(new Uri('https://example.com'));

        self::assertSame(HttpMethod::Post, $configuration->method);
    }

    #[Test]
    #[DataProvider('provideEventFilteringScenarios')]
    public function shouldTriggerForEventFiltersCorrectly(
        array $configured_events,
        string $test_event,
        bool $expected_result,
    ): void {
        $configuration = new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            $configured_events, // @phpstan-ignore argument.type
        );

        $result = $configuration->shouldTriggerForEvent($test_event);

        self::assertSame($expected_result, $result);
    }

    #[Test]
    public function shouldTriggerForEventHandlesWildcard(): void
    {
        $configuration = new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            ['*'], // @phpstan-ignore argument.type
        );

        self::assertTrue($configuration->shouldTriggerForEvent('AnyEvent'));
        self::assertTrue($configuration->shouldTriggerForEvent('AnotherEvent'));
        self::assertTrue($configuration->shouldTriggerForEvent('YetAnotherEvent'));
    }

    #[Test]
    public function shouldTriggerForEventWithEmptyEventsArrayReturnsFalse(): void
    {
        $configuration = new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            [],
        );

        self::assertFalse($configuration->shouldTriggerForEvent('SomeEvent'));
    }

    #[Test]
    public function toArraySerializesAllProperties(): void
    {
        $configuration = new HalResourceWebhookConfiguration(
            new Uri('https://webhook.example.com/endpoint'),
            ['UserRegistered', 'OrderCreated'], // @phpstan-ignore argument.type
            60,
            2,
            ['Authorization' => 'Bearer secret', 'Content-Type' => 'application/json'], // @phpstan-ignore argument.type
        );

        $result = $configuration->toArray();

        $expected = [
            'uri' => 'https://webhook.example.com/endpoint',
            'events' => ['UserRegistered', 'OrderCreated'],
            'timeout_seconds' => 60,
            'max_retry_attempts' => 2,
            'extra_headers' => ['Authorization' => 'Bearer secret', 'Content-Type' => 'application/json'],
        ];

        self::assertSame($expected, $result);
    }

    #[Test]
    public function fromArrayCreatesConfigurationFromArray(): void
    {
        $array_data = [
            'uri' => 'https://api.example.com/hooks',
            'events' => ['EventA', 'EventB'],
            'timeout_seconds' => 45,
            'max_retry_attempts' => 4,
            'extra_headers' => ['X-API-Key' => 'key123'],
        ];

        $result = HalResourceWebhookConfiguration::fromArray($array_data);

        self::assertSame('https://api.example.com/hooks', (string)$result->uri);
        self::assertSame(['EventA', 'EventB'], $result->events);
        self::assertSame(45, $result->timeout_seconds);
        self::assertSame(4, $result->max_retry_attempts);
        self::assertSame(['X-API-Key' => 'key123'], $result->extra_headers);
    }

    #[Test]
    public function fromArrayHandlesMinimalData(): void
    {
        $array_data = [
            'uri' => 'https://minimal.example.com',
        ];

        $result = HalResourceWebhookConfiguration::fromArray($array_data);

        self::assertSame('https://minimal.example.com', (string)$result->uri);
        self::assertEmpty($result->events);
        self::assertSame(30, $result->timeout_seconds);
        self::assertSame(3, $result->max_retry_attempts);
        self::assertEmpty($result->extra_headers);
    }

    #[Test]
    public function toArrayAndFromArrayRoundTrip(): void
    {
        $original = new HalResourceWebhookConfiguration(
            new Uri('https://test.example.com/webhook'),
            ['TestEvent'], // @phpstan-ignore argument.type
            25,
            1,
            ['Custom-Header' => 'value'], // @phpstan-ignore argument.type
        );

        $array_data = $original->toArray();
        $reconstructed = HalResourceWebhookConfiguration::fromArray($array_data);

        self::assertSame((string)$original->uri, (string)$reconstructed->uri);
        self::assertSame($original->events, $reconstructed->events);
        self::assertSame($original->timeout_seconds, $reconstructed->timeout_seconds);
        self::assertSame($original->max_retry_attempts, $reconstructed->max_retry_attempts);
        self::assertSame($original->extra_headers, $reconstructed->extra_headers);
    }

    #[Test]
    public function fromArrayThrowsExceptionForMissingUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URI is required');

        HalResourceWebhookConfiguration::fromArray([]);
    }

    #[Test]
    #[DataProvider('provideUriFormats')]
    public function acceptsVariousUriFormats(UriInterface|string $uri, string $expected_string): void
    {
        $configuration = new HalResourceWebhookConfiguration($uri);

        self::assertSame($expected_string, (string)$configuration->uri);
    }

    #[Test]
    public function handlesComplexEventNames(): void
    {
        $complex_events = [
            'App\\Events\\UserRegistered',
            'Domain\\Order\\OrderCompleted',
            'System.Notification.Sent',
            'webhook-event-name',
        ];

        $configuration = new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            $complex_events, // @phpstan-ignore argument.type
        );

        foreach ($complex_events as $event) {
            self::assertTrue($configuration->shouldTriggerForEvent($event));
        }

        self::assertFalse($configuration->shouldTriggerForEvent('NonConfiguredEvent'));
    }

    #[Test]
    public function preservesExtraHeadersStructure(): void
    {
        $extra_headers = [
            'Authorization' => 'Bearer token',
            'X-Webhook-Source' => 'pinch-framework',
            'Content-Type' => 'application/hal+json',
            'X-Custom' => ['value1', 'value2'],
        ];

        $configuration = new HalResourceWebhookConfiguration(
            new Uri('https://example.com'),
            [],
            30,
            3,
            $extra_headers, // @phpstan-ignore argument.type
        );

        self::assertSame($extra_headers, $configuration->extra_headers);
    }

    public static function provideEventFilteringScenarios(): \Generator
    {
        yield 'specific event matches' => [
            ['UserCreated', 'OrderCompleted'],
            'UserCreated',
            true,
        ];

        yield 'specific event does not match' => [
            ['UserCreated', 'OrderCompleted'],
            'ProductUpdated',
            false,
        ];

        yield 'wildcard matches any event' => [
            ['*'], // @phpstan-ignore argument.type
            'AnyEventName',
            true,
        ];

        yield 'mixed specific and wildcard' => [
            ['UserCreated', '*'], // @phpstan-ignore argument.type
            'RandomEvent',
            true,
        ];

        yield 'case sensitive matching' => [
            ['UserCreated'],
            'usercreated',
            false,
        ];

        yield 'empty events array matches nothing' => [
            [],
            'SomeEvent',
            false,
        ];
    }

    public static function provideUriFormats(): \Generator
    {
        yield 'string URI' => [
            'https://string.example.com/webhook',
            'https://string.example.com/webhook',
        ];

        yield 'UriInterface object' => [
            new Uri('https://object.example.com/hook'),
            'https://object.example.com/hook',
        ];

        yield 'URI with query parameters' => [
            'https://example.com/webhook?token=abc123',
            'https://example.com/webhook?token=abc123',
        ];

        yield 'URI with port' => [
            'https://example.com:8443/webhook',
            'https://example.com:8443/webhook',
        ];
    }
}
