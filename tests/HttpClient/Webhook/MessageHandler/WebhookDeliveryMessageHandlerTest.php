<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\MessageHandler;

use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\MessageSignature\HttpMessageSignatureFactory;
use PhoneBurner\Pinch\Component\HttpClient\Event\WebhookDeliveryCompleted;
use PhoneBurner\Pinch\Component\HttpClient\Event\WebhookDeliveryFailed;
use PhoneBurner\Pinch\Component\HttpClient\Event\WebhookDeliveryStarted;
use PhoneBurner\Pinch\Component\HttpClient\HttpClient;
use PhoneBurner\Pinch\Component\HttpClient\HttpClientFactory;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage;
use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use PhoneBurner\Pinch\Framework\Http\Request\RequestFactory;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\MessageHandler\WebhookDeliveryMessageHandler;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\HttpClient\Webhook\MockWebhookConfiguration;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\HttpClient\Webhook\MockWebhookDeliveryMessage;
use PhoneBurner\Pinch\String\Encoding\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\Uuid;

final class WebhookDeliveryMessageHandlerTest extends TestCase
{
    private HttpClientFactory&MockObject $http_client_factory;

    private HttpMessageSignatureFactory&MockObject $signature_factory;

    private MessageBus&MockObject $message_bus;

    private EventDispatcherInterface&MockObject $event_dispatcher;

    private WebhookDeliveryMessageHandler $handler;

    protected function setUp(): void
    {
        $this->http_client_factory = $this->createMock(HttpClientFactory::class);
        $request_factory = new RequestFactory();
        $this->signature_factory = $this->createMock(HttpMessageSignatureFactory::class);
        $this->message_bus = $this->createMock(MessageBus::class);
        $this->event_dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new WebhookDeliveryMessageHandler(
            $this->http_client_factory,
            $request_factory,
            $this->signature_factory,
            $this->message_bus,
            $this->event_dispatcher,
        );
    }

    #[Test]
    public function successfulWebhookDeliveryDispatchesCorrectEvents(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage();
        $response = $this->createMock(ResponseInterface::class);
        $http_client = $this->createMock(HttpClient::class);

        $this->setupSuccessfulHttpClientCall($http_client, $response);
        $this->setupRequestCreationAndSigning();

        $response->method('getStatusCode')->willReturn(200);

        // Expect events to be dispatched in correct order
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($message): WebhookDeliveryCompleted|WebhookDeliveryStarted {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 1) {
                    self::assertInstanceOf(WebhookDeliveryStarted::class, $event);
                    self::assertSame($message, $event->message);
                } else {
                    self::assertInstanceOf(WebhookDeliveryCompleted::class, $event);
                    self::assertSame($message, $event->message);
                }

                return $event;
            });

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function failedWebhookDeliveryWithNonSuccessResponseDispatchesFailedEvent(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage();
        $response = $this->createMock(ResponseInterface::class);
        $http_client = $this->createMock(HttpClient::class);

        $this->setupSuccessfulHttpClientCall($http_client, $response);
        $this->setupRequestCreationAndSigning();

        $response->method('getStatusCode')->willReturn(500);

        // Expect events to be dispatched
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($message): WebhookDeliveryFailed|WebhookDeliveryStarted {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 1) {
                    self::assertInstanceOf(WebhookDeliveryStarted::class, $event);
                } else {
                    self::assertInstanceOf(WebhookDeliveryFailed::class, $event);
                    self::assertSame($message, $event->message);
                    self::assertTrue($event->retryable);
                    self::assertNull($event->exception);
                }

                return $event;
            });

        // Message should be retried with next attempt
        $this->message_bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($retry_message) use ($message): bool {
                return $retry_message instanceof WebhookDeliveryMessage &&
                       $retry_message->attempt === $message->attempt + 1;
            }));

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function exceptionDuringWebhookDeliveryDispatchesFailedEventWithException(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage();
        $exception = new \RuntimeException('Connection failed');
        $http_client = $this->createMock(HttpClient::class);

        $this->http_client_factory->expects($this->once())
            ->method('createHttpClient')
            ->willReturn($http_client);

        $http_client->method('sendRequest')
            ->willThrowException($exception);
        $this->setupRequestCreationAndSigning();

        // Expect events to be dispatched
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($message, $exception): WebhookDeliveryFailed|WebhookDeliveryStarted {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 1) {
                    self::assertInstanceOf(WebhookDeliveryStarted::class, $event);
                } else {
                    self::assertInstanceOf(WebhookDeliveryFailed::class, $event);
                    self::assertSame($message, $event->message);
                    self::assertTrue($event->retryable);
                    self::assertSame($exception, $event->exception);
                }

                return $event;
            });

        // Message should be retried
        // Message should be retried with next attempt
        $this->message_bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($retry_message) use ($message): bool {
                return $retry_message instanceof WebhookDeliveryMessage &&
                       $retry_message->attempt === $message->attempt + 1;
            }));

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function maxRetryAttemptsReachedDoesNotRetryMessage(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage(attempt: 5, max_retry_attempts: 5);
        $exception = new \RuntimeException('Connection failed');
        $http_client = $this->createMock(HttpClient::class);

        $this->http_client_factory->expects($this->once())
            ->method('createHttpClient')
            ->willReturn($http_client);

        $http_client->method('sendRequest')
            ->willThrowException($exception);
        $this->setupRequestCreationAndSigning();

        // Expect events to be dispatched
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($message): WebhookDeliveryFailed|WebhookDeliveryStarted {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 1) {
                    self::assertInstanceOf(WebhookDeliveryStarted::class, $event);
                } else {
                    self::assertInstanceOf(WebhookDeliveryFailed::class, $event);
                    self::assertSame($message, $event->message);
                    self::assertFalse($event->retryable); // Should not be retryable
                }

                return $event;
            });

        // Message should NOT be retried
        $this->message_bus->expects($this->never())
            ->method('dispatch');

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function httpClientIsCreatedWithCorrectTimeoutConfiguration(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage(
            connect_timeout_seconds: 10,
            request_timeout_seconds: 30,
        );
        $response = $this->createMock(ResponseInterface::class);
        $http_client = $this->createMock(HttpClient::class);

        $this->http_client_factory->expects($this->once())
            ->method('createHttpClient')
            ->with(
                request_timeout_seconds: 30,
                connect_timeout_seconds: 10,
            )
            ->willReturn($http_client);

        $this->setupSuccessfulHttpClientCall($http_client, $response);
        $this->setupRequestCreationAndSigning();

        $response->method('getStatusCode')->willReturn(200);
        $this->event_dispatcher->method('dispatch')->willReturnArgument(0);

        // Act
        ($this->handler)($message);
    }

    #[Test]
    #[DataProvider('providePayloadTypes')]
    public function createAndSignRequestHandlesDifferentPayloadTypes(
        \JsonSerializable|\Stringable|string|array $payload,
        string $expected_body,
    ): void {
        // Arrange
        $webhook_id = Uuid::uuid4();
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://example.com/webhook');

        $configuration = new MockWebhookConfiguration(
            uri: $uri,
            extra_headers: ['X-Custom-Header' => 'custom-value'],
        );

        $message = new MockWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            payload: $payload,
        );
        $request = $this->createMock(RequestInterface::class);
        $signed_request = $this->createMock(RequestInterface::class);

        // Expect request to have correct headers and method
        $this->signature_factory->expects($this->once())
            ->method('sign')
            ->with(self::callback(function (RequestInterface $request) use ($webhook_id): bool {
                $headers = $request->getHeaders();
                return $request->getMethod() === 'POST' &&
                       isset($headers[HttpHeader::CONTENT_TYPE]) && $headers[HttpHeader::CONTENT_TYPE][0] === ContentType::JSON &&
                       isset($headers[HttpHeader::IDEMPOTENCY_KEY]) && $headers[HttpHeader::IDEMPOTENCY_KEY][0] === $webhook_id->toString() &&
                       isset($headers['X-Custom-Header']) && $headers['X-Custom-Header'][0] === 'custom-value';
            }))
            ->willReturn($signed_request);

        // Act
        $result = $this->handler->createAndSignRequest($message);

        // Assert
        self::assertSame($signed_request, $result);
    }

    #[Test]
    public function createAndSignRequestAddsIdempotencyKeyHeader(): void
    {
        // Arrange
        $webhook_id = Uuid::uuid4();
        $uri = $this->createMock(UriInterface::class);
        $configuration = new MockWebhookConfiguration(uri: $uri);
        $message = new MockWebhookDeliveryMessage(
            configuration: $configuration,
            webhook_id: $webhook_id,
            payload: ['test' => 'data'],
        );

        $request = $this->createMock(RequestInterface::class);
        $signed_request = $this->createMock(RequestInterface::class);

        // Expect request to have idempotency key header set
        $this->signature_factory->expects($this->once())
            ->method('sign')
            ->with(self::callback(function (RequestInterface $request) use ($webhook_id): bool {
                $headers = $request->getHeaders();
                return isset($headers[HttpHeader::IDEMPOTENCY_KEY]) &&
                       $headers[HttpHeader::IDEMPOTENCY_KEY][0] === $webhook_id->toString();
            }))
            ->willReturn($signed_request);

        // Act
        $this->handler->createAndSignRequest($message);
    }

    #[Test]
    public function createAndSignRequestIncludesExtraHeaders(): void
    {
        // Arrange
        $extra_headers = [
            'Authorization' => 'Bearer token123',
            'X-Custom-Header' => 'custom-value',
        ];

        $uri = $this->createMock(UriInterface::class);
        $configuration = new MockWebhookConfiguration(
            uri: $uri,
            extra_headers: $extra_headers,
        );

        $message = new MockWebhookDeliveryMessage(
            configuration: $configuration,
            payload: 'test data',
        );

        $signed_request = $this->createMock(RequestInterface::class);

        // Expect extra headers to be included in the request
        $this->signature_factory->expects($this->once())
            ->method('sign')
            ->with(self::callback(function (RequestInterface $request) use ($extra_headers): bool {
                $headers = $request->getHeaders();
                foreach ($extra_headers as $key => $value) {
                    if (! isset($headers[$key]) || $headers[$key][0] !== $value) {
                        return false;
                    }
                }
                return true;
            }))
            ->willReturn($signed_request);

        // Act
        $this->handler->createAndSignRequest($message);
    }

    #[Test]
    public function webhookDeliveryWithSuccessfulResponseIncludesElapsedTimeInEvent(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage();
        $response = $this->createMock(ResponseInterface::class);
        $http_client = $this->createMock(HttpClient::class);

        $this->setupSuccessfulHttpClientCall($http_client, $response);
        $this->setupRequestCreationAndSigning();
        $response->method('getStatusCode')->willReturn(200);

        // Expect WebhookDeliveryCompleted event to include elapsed time
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 2) {
                    self::assertInstanceOf(WebhookDeliveryCompleted::class, $event);
                    self::assertNotNull($event->elapsed_time);
                }

                return $event;
            });

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function webhookDeliveryFailedEventIncludesRequestAndResponseWhenAvailable(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage();
        $response = $this->createMock(ResponseInterface::class);
        $http_client = $this->createMock(HttpClient::class);

        $this->setupSuccessfulHttpClientCall($http_client, $response);
        $this->setupRequestCreationAndSigning();
        $response->method('getStatusCode')->willReturn(500);

        // Expect WebhookDeliveryFailed event to include request and response
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($message, $response) {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 2) {
                    self::assertInstanceOf(WebhookDeliveryFailed::class, $event);
                    self::assertSame($message, $event->message);
                    self::assertInstanceOf(RequestInterface::class, $event->request);
                    self::assertSame($response, $event->response);
                    self::assertTrue($event->retryable);
                    self::assertNull($event->exception);
                }

                return $event;
            });

        // Message should be retried
        // Message should be retried with next attempt
        $this->message_bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($retry_message) use ($message): bool {
                return $retry_message instanceof WebhookDeliveryMessage &&
                       $retry_message->attempt === $message->attempt + 1;
            }));

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function webhookDeliveryFailedEventWhenExceptionOccursBeforeRequestCreation(): void
    {
        // Arrange
        $message = $this->createMockWebhookDeliveryMessage();
        $exception = new \RuntimeException('Request creation failed');

        // Simulate request creation failure by throwing exception in signature factory
        $this->signature_factory->method('sign')
            ->willThrowException($exception);

        // Expect WebhookDeliveryFailed event with null request and response
        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($message, $exception) {
                static $call_count = 0;
                ++$call_count;

                if ($call_count === 2) {
                    self::assertInstanceOf(WebhookDeliveryFailed::class, $event);
                    self::assertSame($message, $event->message);
                    self::assertNull($event->request);
                    self::assertNull($event->response);
                    self::assertTrue($event->retryable);
                    self::assertSame($exception, $event->exception);
                }

                return $event;
            });

        // Message should be retried
        // Message should be retried with next attempt
        $this->message_bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($retry_message) use ($message): bool {
                return $retry_message instanceof WebhookDeliveryMessage &&
                       $retry_message->attempt === $message->attempt + 1;
            }));

        // Act
        ($this->handler)($message);
    }

    #[Test]
    public function createAndSignRequestUsesPostMethodForAllRequests(): void
    {
        // Arrange
        $message = new MockWebhookDeliveryMessage();
        $request = $this->createMock(RequestInterface::class);
        $signed_request = $this->createMock(RequestInterface::class);

        // Expect POST method to be used
        $this->signature_factory->expects($this->once())
            ->method('sign')
            ->with(self::callback(function (RequestInterface $request): bool {
                return $request->getMethod() === 'POST';
            }))
            ->willReturn($signed_request);

        // Act
        $this->handler->createAndSignRequest($message);
    }

    #[Test]
    public function createAndSignRequestSetsContentTypeToJson(): void
    {
        // Arrange
        $message = new MockWebhookDeliveryMessage();
        $signed_request = $this->createMock(RequestInterface::class);

        // Expect Content-Type header to be set to JSON
        $this->signature_factory->expects($this->once())
            ->method('sign')
            ->with(self::callback(function (RequestInterface $request): bool {
                $headers = $request->getHeaders();
                return isset($headers[HttpHeader::CONTENT_TYPE]) &&
                       $headers[HttpHeader::CONTENT_TYPE][0] === ContentType::JSON;
            }))
            ->willReturn($signed_request);

        // Act
        $this->handler->createAndSignRequest($message);
    }

    public static function providePayloadTypes(): \Generator
    {
        yield 'string payload' => [
            'simple string',
            'simple string',
        ];

        yield 'array payload' => [
            ['key' => 'value', 'number' => 42],
            Json::encode(['key' => 'value', 'number' => 42]),
        ];

        $json_serializable = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['serialized' => true];
            }
        };

        yield 'JsonSerializable payload' => [
            $json_serializable,
            Json::encode(['serialized' => true]),
        ];

        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable object';
            }
        };

        yield 'Stringable payload' => [
            $stringable,
            'stringable object',
        ];

        yield 'StreamInterface payload' => [
            'stream_content',
            'stream_content',
        ];
    }

    private function createMockWebhookDeliveryMessage(
        int $attempt = 1,
        int $max_retry_attempts = 3,
        int $connect_timeout_seconds = 5,
        int $request_timeout_seconds = 10,
    ): WebhookDeliveryMessage {
        $configuration = new MockWebhookConfiguration(
            connect_timeout_seconds: $connect_timeout_seconds,
            request_timeout_seconds: $request_timeout_seconds,
            max_retry_attempts: $max_retry_attempts,
        );

        return new MockWebhookDeliveryMessage(
            configuration: $configuration,
            attempt: $attempt,
        );
    }

    private function setupSuccessfulHttpClientCall(
        HttpClient&MockObject $http_client,
        ResponseInterface&MockObject $response,
    ): void {
        $this->http_client_factory->expects($this->once())
            ->method('createHttpClient')
            ->willReturn($http_client);

        $http_client->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);
    }

    private function setupRequestCreationAndSigning(): void
    {
        $this->signature_factory->method('sign')
            ->willReturnCallback(fn(RequestInterface $req): MessageInterface => $req);
    }
}
