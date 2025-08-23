<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\Message;

use Laminas\Diactoros\Uri;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\HalResource;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Message\HalResourceWebhookDeliveryMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Psr\Link\LinkInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class HalResourceWebhookDeliveryMessageTest extends TestCase
{
    private SimpleEventWebhookConfiguration $configuration;
    private UuidInterface $webhook_id;
    private \DateTimeImmutable $timestamp;
    private HalResource $hal_resource;

    protected function setUp(): void
    {
        $this->configuration = new SimpleEventWebhookConfiguration(
            uri: 'https://webhook.example.com/endpoint',
            events: ['UserCreated', 'UserUpdated'], // @phpstan-ignore argument.type
            extra_headers: ['X-Webhook-Secret' => 'secret123'], // @phpstan-ignore argument.type
            connect_timeout_seconds: 10,
            request_timeout_seconds: 30,
            max_retry_attempts: 3,
        );

        $this->webhook_id = Uuid::uuid4();
        $this->timestamp = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');

        $this->hal_resource = HalResource::make(
            properties: [
                'id' => 'user-123',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'created_at' => '2024-01-15T10:30:00+00:00',
            ],
            links: [],
            embedded: [],
        );
    }

    #[Test]
    public function constructorCreatesInstanceWithRequiredProperties(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertInstanceOf(WebhookDeliveryMessage::class, $message);
        self::assertSame($this->configuration, $message->configuration);
        self::assertSame($this->webhook_id, $message->webhook_id);
        self::assertSame($this->timestamp, $message->timestamp);
        self::assertSame($this->hal_resource, $message->payload);
        self::assertSame(1, $message->attempt);
    }

    #[Test]
    public function constructorCreatesInstanceWithCustomAttemptNumber(): void
    {
        $attempt = 5;

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
            attempt: $attempt,
        );

        self::assertSame($attempt, $message->attempt);
    }

    #[Test]
    public function implementsWebhookDeliveryMessageInterface(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertInstanceOf(WebhookDeliveryMessage::class, $message);
    }

    #[Test]
    public function configurationPropertyIsAccessible(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertSame($this->configuration, $message->configuration);
        self::assertSame('https://webhook.example.com/endpoint', (string)$message->configuration->uri);
        self::assertSame(['UserCreated', 'UserUpdated'], $message->configuration->events);
        self::assertSame(['X-Webhook-Secret' => 'secret123'], $message->configuration->extra_headers);
    }

    #[Test]
    public function webhookIdPropertyIsAccessible(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertSame($this->webhook_id, $message->webhook_id);
        self::assertTrue(Uuid::isValid((string)$message->webhook_id));
    }

    #[Test]
    public function timestampPropertyIsAccessible(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertSame($this->timestamp, $message->timestamp);
        self::assertSame('2024-01-15T10:30:00+00:00', $message->timestamp->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function payloadPropertyIsAccessible(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertSame($this->hal_resource, $message->payload);
        self::assertInstanceOf(HalResource::class, $message->payload);
        self::assertTrue($message->payload->hasProperty('id'));
        self::assertSame('user-123', $message->payload->getProperty('id'));
    }

    #[Test]
    public function attemptPropertyIsAccessible(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertSame(1, $message->attempt);
    }

    #[Test]
    public function withNextAttemptReturnsNewInstanceWithIncrementedAttempt(): void
    {
        $original_message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
            attempt: 1,
        );

        $next_message = $original_message->withNextAttempt();

        // Verify it returns a new instance
        self::assertNotSame($original_message, $next_message);
        self::assertInstanceOf(HalResourceWebhookDeliveryMessage::class, $next_message);

        // Verify attempt is incremented
        self::assertSame(1, $original_message->attempt);
        self::assertSame(2, $next_message->attempt);

        // Verify other properties remain the same
        self::assertSame($this->configuration, $next_message->configuration);
        self::assertSame($this->webhook_id, $next_message->webhook_id);
        self::assertSame($this->timestamp, $next_message->timestamp);
        self::assertSame($this->hal_resource, $next_message->payload);
    }

    #[Test]
    #[DataProvider('provideAttemptNumbers')]
    public function withNextAttemptIncrementsFromVariousStartingAttempts(int $starting_attempt): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
            attempt: $starting_attempt,
        );

        $next_message = $message->withNextAttempt();

        self::assertSame($starting_attempt + 1, $next_message->attempt);
    }

    public static function provideAttemptNumbers(): \Generator
    {
        yield 'first attempt' => [1];
        yield 'second attempt' => [2];
        yield 'third attempt' => [3];
        yield 'fifth attempt' => [5];
        yield 'tenth attempt' => [10];
    }

    #[Test]
    public function halResourcePayloadCanBeJsonSerialized(): void
    {
        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        $serialized = \json_encode($message->payload);
        self::assertIsString($serialized);
        $decoded = \json_decode($serialized, true);

        self::assertIsString($serialized);
        self::assertIsArray($decoded);
        self::assertSame('user-123', $decoded['id']);
        self::assertSame('John Doe', $decoded['name']);
        self::assertSame('john@example.com', $decoded['email']);
        self::assertSame('2024-01-15T10:30:00+00:00', $decoded['created_at']);
    }

    #[Test]
    public function worksWithHalResourceContainingLinks(): void
    {
        $link = $this->createMock(LinkInterface::class);
        $link->method('getHref')->willReturn('/api/users/123');
        $link->method('getRels')->willReturn(['self']);
        $link->method('isTemplated')->willReturn(false);
        $link->method('getAttributes')->willReturn([]);

        $hal_resource_with_links = HalResource::make(
            properties: ['id' => 'user-123'],
            links: [$link],
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $hal_resource_with_links,
        );

        self::assertSame($hal_resource_with_links, $message->payload);
        self::assertCount(1, $message->payload->getLinks());
    }

    #[Test]
    public function worksWithHalResourceContainingEmbeddedResources(): void
    {
        $embedded_resource = HalResource::make(['id' => 'address-456', 'street' => '123 Main St']);

        $hal_resource_with_embedded = HalResource::make(
            properties: ['id' => 'user-123'],
            embedded: ['address' => $embedded_resource],
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $hal_resource_with_embedded,
        );

        self::assertSame($hal_resource_with_embedded, $message->payload);
        self::assertTrue($message->payload->hasEmbeddedResource('address'));
        self::assertInstanceOf(HalResource::class, $message->payload->getEmbeddedResource('address'));
    }

    #[Test]
    public function worksWithComplexHalResourceStructure(): void
    {
        $link = $this->createMock(LinkInterface::class);
        $link->method('getHref')->willReturn('/api/users/123');
        $link->method('getRels')->willReturn(['self']);
        $link->method('isTemplated')->willReturn(false);
        $link->method('getAttributes')->willReturn([]);

        $embedded_address = HalResource::make([
            'id' => 'address-456',
            'street' => '123 Main St',
            'city' => 'New York',
            'zip' => '10001',
        ]);

        $embedded_orders = [
            HalResource::make(['id' => 'order-789', 'total' => 99.99]),
            HalResource::make(['id' => 'order-790', 'total' => 149.50]),
        ];

        $complex_hal_resource = HalResource::make(
            properties: [
                'id' => 'user-123',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'active' => true,
                'balance' => 249.49,
                'tags' => ['premium', 'loyal'],
                'metadata' => ['source' => 'api', 'version' => '1.2'],
            ],
            links: [$link],
            embedded: [
                'address' => $embedded_address,
                'orders' => $embedded_orders,
            ],
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $complex_hal_resource,
        );

        // Verify the message contains the complex structure
        self::assertSame($complex_hal_resource, $message->payload);

        // Verify properties
        self::assertSame('user-123', $message->payload->getProperty('id'));
        self::assertSame(['premium', 'loyal'], $message->payload->getProperty('tags'));
        self::assertSame(['source' => 'api', 'version' => '1.2'], $message->payload->getProperty('metadata'));

        // Verify links
        self::assertCount(1, $message->payload->getLinks());

        // Verify embedded resources
        self::assertTrue($message->payload->hasEmbeddedResource('address'));
        self::assertTrue($message->payload->hasEmbeddedResource('orders'));
        self::assertInstanceOf(HalResource::class, $message->payload->getEmbeddedResource('address'));
        self::assertIsArray($message->payload->getEmbeddedResource('orders'));
        self::assertCount(2, $message->payload->getEmbeddedResource('orders'));
    }

    #[Test]
    public function configurationWithDifferentUriTypes(): void
    {
        $uri_object = new Uri('https://api.example.com/webhooks');
        $config_with_uri_object = new SimpleEventWebhookConfiguration(
            uri: $uri_object,
            events: ['TestEvent'], // @phpstan-ignore argument.type
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $config_with_uri_object,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertInstanceOf(UriInterface::class, $message->configuration->uri);
        self::assertSame('https://api.example.com/webhooks', (string)$message->configuration->uri);
    }

    #[Test]
    public function configurationIntegrationWithAllSettings(): void
    {
        $comprehensive_config = new SimpleEventWebhookConfiguration(
            uri: 'https://secure.webhook.example.com/endpoint',
            events: ['UserCreated', 'UserUpdated', 'UserDeleted', 'OrderPlaced'], // @phpstan-ignore argument.type
            extra_headers: [ // @phpstan-ignore argument.type
                'X-Webhook-Secret' => 'super-secret-key',
                'X-API-Version' => 'v2',
                'Authorization' => 'Bearer token123',
                'Content-Type' => 'application/hal+json',
            ],
            connect_timeout_seconds: 15,
            request_timeout_seconds: 45,
            max_retry_attempts: 5,
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $comprehensive_config,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        self::assertSame($comprehensive_config, $message->configuration);
        self::assertSame(15, $message->configuration->connect_timeout_seconds);
        self::assertSame(45, $message->configuration->request_timeout_seconds);
        self::assertSame(5, $message->configuration->max_retry_attempts);
        self::assertCount(4, $message->configuration->extra_headers);
        /** @phpstan-ignore offsetAccess.notFound */
        self::assertSame('application/hal+json', $message->configuration->extra_headers['Content-Type']);
    }

    #[Test]
    public function messageRoutingWithEventConfiguration(): void
    {
        $event_specific_config = new SimpleEventWebhookConfiguration(
            uri: 'https://events.webhook.example.com/handler',
            events: ['UserRegistered', 'PaymentProcessed'], // @phpstan-ignore argument.type
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $event_specific_config,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        // Test event routing logic
        self::assertTrue($message->configuration->shouldTriggerForEvent('UserRegistered'));
        self::assertTrue($message->configuration->shouldTriggerForEvent('PaymentProcessed'));
        self::assertFalse($message->configuration->shouldTriggerForEvent('UserDeleted'));
        self::assertFalse($message->configuration->shouldTriggerForEvent('NonExistentEvent'));
    }

    #[Test]
    public function messageRoutingWithWildcardEventConfiguration(): void
    {
        $wildcard_config = new SimpleEventWebhookConfiguration(
            uri: 'https://catch-all.webhook.example.com/handler',
            events: ['*'], // @phpstan-ignore argument.type
        );

        $message = new HalResourceWebhookDeliveryMessage(
            configuration: $wildcard_config,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        // Test wildcard event routing
        self::assertTrue($message->configuration->shouldTriggerForEvent('UserCreated'));
        self::assertTrue($message->configuration->shouldTriggerForEvent('PaymentProcessed'));
        self::assertTrue($message->configuration->shouldTriggerForEvent('AnyRandomEvent'));
        self::assertTrue($message->configuration->shouldTriggerForEvent('SomeOtherEvent'));
    }

    #[Test]
    public function multipleAttemptChaining(): void
    {
        $original_message = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
            attempt: 1,
        );

        $second_attempt = $original_message->withNextAttempt();
        $third_attempt = $second_attempt->withNextAttempt();
        $fourth_attempt = $third_attempt->withNextAttempt();

        self::assertSame(1, $original_message->attempt);
        self::assertSame(2, $second_attempt->attempt);
        self::assertSame(3, $third_attempt->attempt);
        self::assertSame(4, $fourth_attempt->attempt);

        // Verify all instances are different objects
        self::assertNotSame($original_message, $second_attempt);
        self::assertNotSame($second_attempt, $third_attempt);
        self::assertNotSame($third_attempt, $fourth_attempt);

        // Verify immutability - all other properties remain the same
        self::assertSame($this->configuration, $fourth_attempt->configuration);
        self::assertSame($this->webhook_id, $fourth_attempt->webhook_id);
        self::assertSame($this->timestamp, $fourth_attempt->timestamp);
        self::assertSame($this->hal_resource, $fourth_attempt->payload);
    }

    #[Test]
    public function webhookIdCorrelationAndTracking(): void
    {
        $webhook_id_1 = Uuid::uuid4();
        $webhook_id_2 = Uuid::uuid4();

        $message_1 = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $webhook_id_1,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        $message_2 = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $webhook_id_2,
            timestamp: $this->timestamp,
            payload: $this->hal_resource,
        );

        // Verify messages have different webhook IDs for correlation
        self::assertNotSame($message_1->webhook_id, $message_2->webhook_id);
        self::assertTrue(Uuid::isValid((string)$message_1->webhook_id));
        self::assertTrue(Uuid::isValid((string)$message_2->webhook_id));

        // Verify retry attempts maintain the same webhook ID
        $retry_message_1 = $message_1->withNextAttempt();
        self::assertSame($webhook_id_1, $retry_message_1->webhook_id);
        self::assertSame($message_1->webhook_id, $retry_message_1->webhook_id);
    }

    #[Test]
    public function messageCorrelationWithTimestamp(): void
    {
        $timestamp_1 = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $timestamp_2 = new \DateTimeImmutable('2024-01-15T11:45:00+00:00');

        $message_1 = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $timestamp_1,
            payload: $this->hal_resource,
        );

        $message_2 = new HalResourceWebhookDeliveryMessage(
            configuration: $this->configuration,
            webhook_id: $this->webhook_id,
            timestamp: $timestamp_2,
            payload: $this->hal_resource,
        );

        // Verify timestamps are preserved for correlation
        self::assertSame($timestamp_1, $message_1->timestamp);
        self::assertSame($timestamp_2, $message_2->timestamp);
        self::assertNotSame($message_1->timestamp, $message_2->timestamp);

        // Verify retry attempts maintain the same timestamp
        $retry_message = $message_1->withNextAttempt();
        self::assertSame($timestamp_1, $retry_message->timestamp);
        self::assertSame($message_1->timestamp, $retry_message->timestamp);
    }
}
