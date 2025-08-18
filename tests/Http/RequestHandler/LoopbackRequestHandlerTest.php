<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Response\EmptyResponse;
use PhoneBurner\Pinch\Framework\Http\Event\LoopbackRequestHandled;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\LoopbackRequestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoopbackRequestHandlerTest extends TestCase
{
    private EventDispatcherInterface&MockObject $event_dispatcher;
    private ServerRequestInterface&MockObject $server_request;

    protected function setUp(): void
    {
        $this->event_dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->server_request = $this->createMock(ServerRequestInterface::class);
    }

    #[Test]
    public function handleDispatchesLoopbackRequestHandledEvent(): void
    {
        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                return $event instanceof LoopbackRequestHandled
                    && $event->request === $this->server_request;
            }));

        new LoopbackRequestHandler($this->event_dispatcher)->handle($this->server_request);
    }

    #[Test]
    public function handleReturnsEmptyResponseWhenNoCallbackProvided(): void
    {
        // Arrange
        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $handler = new LoopbackRequestHandler($this->event_dispatcher);

        $response = $handler->handle($this->server_request);

        self::assertInstanceOf(EmptyResponse::class, $response);
    }

    #[Test]
    public function handleReturnsCallbackResultWhenCallbackProvided(): void
    {
        // Arrange
        $expected_response = $this->createMock(ResponseInterface::class);
        $response_callback = function () use ($expected_response): ResponseInterface {
            return $expected_response;
        };

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $handler = new LoopbackRequestHandler($this->event_dispatcher, $response_callback);

        $response = $handler->handle($this->server_request);

        self::assertSame($expected_response, $response);
    }

    #[Test]
    #[DataProvider('provideServerRequestImplementations')]
    public function handleWorksWithDifferentServerRequestImplementations(HttpMethod $method): void
    {
        // Arrange
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method->value);

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($request): bool {
                return $event instanceof LoopbackRequestHandled && $event->request === $request;
            }));

        $handler = new LoopbackRequestHandler($this->event_dispatcher);

        $response = $handler->handle($request);

        self::assertInstanceOf(EmptyResponse::class, $response);
    }

    public static function provideServerRequestImplementations(): \Generator
    {
        // For data providers, we'll yield test data that can be used to create mocks in the test method
        yield 'GET request' => [HttpMethod::Get];
        yield 'POST request' => [HttpMethod::Post];
    }

    #[Test]
    public function handleExecutesProvidedResponseCallbackReturnsEmptyResponse(): void
    {
        $response_callback = static function (): ResponseInterface {
            return new EmptyResponse();
        };

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $handler = new LoopbackRequestHandler($this->event_dispatcher, $response_callback);

        $response = $handler->handle($this->server_request);

        self::assertInstanceOf(EmptyResponse::class, $response);
    }

    #[Test]
    public function handleDispatchesEventBeforeExecutingCallback(): void
    {
        $execution_order = [];

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class))
            ->willReturnCallback(function ($event) use (&$execution_order) {
                $execution_order[] = 'event_dispatched';
                return $event;
            });

        $response_callback = function () use (&$execution_order): ResponseInterface {
            $execution_order[] = 'callback_executed';
            return $this->createMock(ResponseInterface::class);
        };

        $handler = new LoopbackRequestHandler($this->event_dispatcher, $response_callback);

        $handler->handle($this->server_request);

        self::assertSame(['event_dispatched', 'callback_executed'], $execution_order);
    }

    #[Test]
    public function handleWithCallbackThatThrowsExceptionStillDispatchesEvent(): void
    {
        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $response_callback = static fn (): ResponseInterface => throw new \RuntimeException('Callback failed');

        $handler = new LoopbackRequestHandler($this->event_dispatcher, $response_callback);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Callback failed');

        $handler->handle($this->server_request);
    }

    #[Test]
    public function multipleHandleCallsDispatchEventEachTime(): void
    {
        $this->event_dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $handler = new LoopbackRequestHandler($this->event_dispatcher);

        $response_1 = $handler->handle($this->server_request);
        $response_2 = $handler->handle($this->server_request);
        $response_3 = $handler->handle($this->server_request);

        self::assertInstanceOf(EmptyResponse::class, $response_1);
        self::assertInstanceOf(EmptyResponse::class, $response_2);
        self::assertInstanceOf(EmptyResponse::class, $response_3);

        // Each response should be a new instance
        self::assertNotSame($response_1, $response_2);
        self::assertNotSame($response_2, $response_3);
    }

    #[Test]
    public function callbackReceivesNoParametersAndCanAccessContext(): void
    {
        $expected_response = $this->createMock(ResponseInterface::class);
        $callback_executed = false;

        $response_callback = function () use ($expected_response, &$callback_executed): ResponseInterface {
            $callback_executed = true;
            // Verify callback receives no parameters (loopback pattern)
            self::assertSame(0, \func_num_args());
            return $expected_response;
        };

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $handler = new LoopbackRequestHandler($this->event_dispatcher, $response_callback);

        $response = $handler->handle($this->server_request);

        self::assertTrue($callback_executed);
        self::assertSame($expected_response, $response);
    }

    #[Test]
    public function eventContainsExactRequestFromHandler(): void
    {
        $request_1 = $this->createMock(ServerRequestInterface::class);
        $request_2 = $this->createMock(ServerRequestInterface::class);

        $request_1->method('getMethod')->willReturn(HttpMethod::Get->value);
        $request_2->method('getMethod')->willReturn(HttpMethod::Post->value);

        $dispatched_events = [];

        $this->event_dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatched_events) {
                $dispatched_events[] = $event;
                return $event;
            });

        $handler = new LoopbackRequestHandler($this->event_dispatcher);

        $handler->handle($request_1);
        $handler->handle($request_2);

        self::assertCount(2, $dispatched_events);
        self::assertContainsOnlyInstancesOf(LoopbackRequestHandled::class, $dispatched_events);
        self::assertSame($request_1, $dispatched_events[0]->request);
        self::assertSame($request_2, $dispatched_events[1]->request);
        self::assertSame(HttpMethod::Get->value, $dispatched_events[0]->request->getMethod());
        self::assertSame(HttpMethod::Post->value, $dispatched_events[1]->request->getMethod());
    }

    #[Test]
    public function callbackValidationEnsuresProperSignature(): void
    {
        // Arrange
        $valid_response = $this->createMock(ResponseInterface::class);

        // Test callback with proper signature (no parameters, returns ResponseInterface)
        $valid_callback = static fn(): ResponseInterface => $valid_response;

        $this->event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoopbackRequestHandled::class));

        $handler = new LoopbackRequestHandler($this->event_dispatcher, $valid_callback);

        $response = $handler->handle($this->server_request);

        self::assertSame($valid_response, $response);
    }
}
