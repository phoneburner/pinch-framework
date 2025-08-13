<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\Message;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Message\SimpleEventWebhookDeliveryMessage;
use PhoneBurner\Pinch\Time\Standards\Rfc3339;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SimpleEventWebhookDeliveryMessageTest extends TestCase
{
    #[Test]
    public function implementsWebhookDeliveryMessageInterface(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123, 'email' => 'test@example.com'];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        self::assertInstanceOf(WebhookDeliveryMessage::class, $message);
    }

    #[Test]
    public function constructorSetsAllPropertiesCorrectly(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable('2025-01-15T10:30:00Z');
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123, 'email' => 'test@example.com'];
        $attempt = 3;

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
            attempt: $attempt,
        );

        self::assertSame($configuration, $message->configuration);
        self::assertSame($webhook_id, $message->webhook_id);
        self::assertSame($timestamp, $message->timestamp);
        self::assertSame($event_class, $message->event_class);
        self::assertSame($event_payload, $message->event_payload);
        self::assertSame($attempt, $message->attempt);
    }

    #[Test]
    public function constructorUsesDefaultAttemptValueOfOne(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        self::assertSame(1, $message->attempt);
    }

    #[Test]
    #[DataProvider('provideAttemptNumbers')]
    public function constructorAcceptsVariousAttemptNumbers(int $attempt): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
            attempt: $attempt,
        );

        self::assertSame($attempt, $message->attempt);
    }

    public static function provideAttemptNumbers(): \Generator
    {
        yield 'first attempt' => [1];
        yield 'second attempt' => [2];
        yield 'fifth attempt' => [5];
        yield 'tenth attempt' => [10];
        yield 'large number' => [999];
    }

    #[Test]
    public function withNextAttemptReturnsNewInstanceWithIncrementedAttempt(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123];
        $initial_attempt = 3;

        $original_message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
            attempt: $initial_attempt,
        );

        $next_message = $original_message->withNextAttempt();

        // Verify new instance is created
        self::assertNotSame($original_message, $next_message);

        // Verify attempt is incremented
        self::assertSame($initial_attempt + 1, $next_message->attempt);

        // Verify all other properties remain the same
        self::assertSame($configuration, $next_message->configuration);
        self::assertSame($webhook_id, $next_message->webhook_id);
        self::assertSame($timestamp, $next_message->timestamp);
        self::assertSame($event_class, $next_message->event_class);
        self::assertSame($event_payload, $next_message->event_payload);

        // Verify original message is unchanged
        self::assertSame($initial_attempt, $original_message->attempt);
    }

    #[Test]
    public function withNextAttemptWorksMultipleTimes(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
            attempt: 1,
        );

        $second_attempt = $message->withNextAttempt();
        $third_attempt = $second_attempt->withNextAttempt();
        $fourth_attempt = $third_attempt->withNextAttempt();

        self::assertSame(1, $message->attempt);
        self::assertSame(2, $second_attempt->attempt);
        self::assertSame(3, $third_attempt->attempt);
        self::assertSame(4, $fourth_attempt->attempt);
    }

    #[Test]
    public function payloadPropertyReturnsCorrectStructure(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable('2025-01-15T10:30:00Z');
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123, 'email' => 'test@example.com', 'name' => 'John Doe'];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        $expected_payload = [
            'webhook_id' => $webhook_id,
            'timestamp' => $timestamp->format(Rfc3339::DATETIME),
            'event_class' => $event_class,
            'event_payload' => $event_payload,
        ];

        self::assertSame($expected_payload, $message->payload);
    }

    #[Test]
    public function payloadUsesRfc3339DateTimeFormat(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable('2025-01-15T10:30:45+05:00');
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        $expected_timestamp = $timestamp->format(Rfc3339::DATETIME);
        self::assertSame($expected_timestamp, $message->payload['timestamp']);
        self::assertStringContainsString('2025-01-15T10:30:45+05:00', $expected_timestamp);
    }

    #[Test]
    #[DataProvider('provideEventPayloads')]
    public function payloadHandlesDifferentEventPayloadTypes(array $event_payload): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\TestEvent';

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        self::assertSame($event_payload, $message->payload['event_payload']);
    }

    public static function provideEventPayloads(): \Generator
    {
        yield 'empty array' => [[]];
        yield 'simple data' => [['id' => 1, 'name' => 'test']];
        yield 'nested data' => [['user' => ['id' => 1, 'profile' => ['email' => 'test@example.com']]]];
        yield 'array with mixed types' => [['id' => 1, 'active' => true, 'score' => 98.5, 'tags' => ['tag1', 'tag2']]];
        yield 'large payload' => [['data' => \array_fill(0, 100, 'test_value')]];
    }

    #[Test]
    #[DataProvider('provideEventClasses')]
    public function messageHandlesDifferentEventClasses(string $event_class): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_payload = ['test' => 'data'];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        self::assertSame($event_class, $message->event_class);
        self::assertSame($event_class, $message->payload['event_class']);
    }

    public static function provideEventClasses(): \Generator
    {
        yield 'simple class name' => ['UserCreated'];
        yield 'namespaced class' => ['App\\Events\\UserCreated'];
        yield 'deep namespace' => ['App\\Domain\\User\\Events\\UserCreated'];
        yield 'with underscores' => ['User_Created_Event'];
        yield 'with numbers' => ['Event123'];
    }

    #[Test]
    public function messageIntegratesWithSimpleEventWebhookConfiguration(): void
    {
        $configuration = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook',
            /** @phpstan-ignore-next-line */
            events: ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated'],
            extra_headers: [],
            connect_timeout_seconds: 30,
            request_timeout_seconds: 60,
            max_retry_attempts: 5,
        );

        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        self::assertSame($configuration, $message->configuration);

        // Verify configuration can determine if this event should trigger
        self::assertTrue($configuration->shouldTriggerForEvent($event_class));
    }

    #[Test]
    public function messageSupportsEventCorrelationAndTracking(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = [
            'user_id' => 123,
            'correlation_id' => 'corr-123-456',
            'trace_id' => 'trace-789-abc',
        ];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        $payload = $message->payload;

        // Verify correlation and tracking data is preserved in payload
        self::assertSame('corr-123-456', $payload['event_payload']['correlation_id']);
        self::assertSame('trace-789-abc', $payload['event_payload']['trace_id']);

        // Verify webhook_id provides delivery correlation
        self::assertInstanceOf(UuidInterface::class, $payload['webhook_id']);
        self::assertSame($webhook_id, $payload['webhook_id']);
    }

    #[Test]
    public function messageRoutingContextIsPreserved(): void
    {
        $configuration = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook',
            /** @phpstan-ignore-next-line */
            events: ['*'], // Wildcard to catch all events
            extra_headers: [],
        );

        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\OrderProcessed';
        $event_payload = ['order_id' => 789, 'status' => 'completed'];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        // Verify all routing context is available
        self::assertSame($configuration, $message->configuration);
        self::assertTrue($configuration->shouldTriggerForEvent($event_class));
        self::assertSame(['*'], $configuration->events);
        self::assertEmpty($configuration->extra_headers);
    }

    #[Test]
    public function payloadIsJsonSerializable(): void
    {
        $configuration = $this->createMockConfiguration();
        $webhook_id = Uuid::uuid4();
        $timestamp = new \DateTimeImmutable();
        $event_class = 'App\\Events\\UserCreated';
        $event_payload = ['user_id' => 123, 'email' => 'test@example.com'];

        $message = new SimpleEventWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            timestamp: $timestamp,
            event_class: $event_class,
            event_payload: $event_payload,
        );

        $json = \json_encode($message->payload);
        self::assertIsString($json);

        $decoded = \json_decode($json, true);
        self::assertIsArray($decoded);
        self::assertSame($webhook_id->toString(), $decoded['webhook_id']);
        self::assertSame($event_class, $decoded['event_class']);
        self::assertSame($event_payload, $decoded['event_payload']);
    }

    private function createMockConfiguration(): SimpleEventWebhookConfiguration
    {
        return new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook',
            /** @phpstan-ignore-next-line */
            events: ['App\\Events\\UserCreated'],
        );
    }
}
