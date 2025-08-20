<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Middleware;

use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Framework\Http\Middleware\AddCorrelationIdHeaderToResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(AddCorrelationIdHeaderToResponse::class)]
final class AddCorrelationIdHeaderToResponseTest extends TestCase
{
    #[Test]
    public function addsCorrelationIdHeaderToResponseWhenNoneExists(): void
    {
        $correlation_id = 'test-correlation-id';
        $middleware = new AddCorrelationIdHeaderToResponse($correlation_id);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, $correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function doesNotOverrideExistingResponseHeaderByDefault(): void
    {
        $correlation_id = 'test-correlation-id';
        $middleware = new AddCorrelationIdHeaderToResponse($correlation_id);

        $request = $this->createMock(ServerRequestInterface::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(true);
        $response->expects($this->never())->method('withHeader');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function copiesCorrelationIdFromRequestWhenResponseHeaderMissing(): void
    {
        $correlation_id = 'middleware-correlation-id';
        $request_correlation_id = 'request-correlation-id';
        $middleware = new AddCorrelationIdHeaderToResponse($correlation_id);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(true);
        $request->method('getHeaderLine')->with(HttpHeader::X_CORRELATION_ID)->willReturn($request_correlation_id);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, $request_correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function overridesExistingResponseHeaderWhenConfigured(): void
    {
        $correlation_id = 'test-correlation-id';
        $middleware = new AddCorrelationIdHeaderToResponse(
            correlation_id: $correlation_id,
            header_name: HttpHeader::X_CORRELATION_ID,
            override_existing_header: true,
        );

        $request = $this->createMock(ServerRequestInterface::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(true);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, $correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function usesCustomHeaderName(): void
    {
        $correlation_id = 'test-correlation-id';
        $custom_header_name = 'X-Custom-Trace-ID';
        $middleware = new AddCorrelationIdHeaderToResponse($correlation_id, $custom_header_name);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with($custom_header_name)->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with($custom_header_name)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with($custom_header_name, $correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function convertsStringableCorrelationIdToString(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return 'stringable-correlation-id';
            }
        };

        $middleware = new AddCorrelationIdHeaderToResponse($stringable);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, 'stringable-correlation-id')
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function prioritizesRequestHeaderOverMiddlewareCorrelationId(): void
    {
        $middleware_correlation_id = 'middleware-id';
        $request_correlation_id = 'request-id';
        $middleware = new AddCorrelationIdHeaderToResponse($middleware_correlation_id);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(true);
        $request->method('getHeaderLine')->with(HttpHeader::X_CORRELATION_ID)->willReturn($request_correlation_id);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, $request_correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function usesMiddlewareCorrelationIdWhenNeitherRequestNorResponseHaveHeader(): void
    {
        $correlation_id = 'middleware-correlation-id';
        $middleware = new AddCorrelationIdHeaderToResponse($correlation_id);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, $correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function overridesRequestHeaderWhenOverrideIsEnabledAndResponseHasNoHeader(): void
    {
        $middleware_correlation_id = 'middleware-id';
        $request_correlation_id = 'request-id';
        $middleware = new AddCorrelationIdHeaderToResponse(
            correlation_id: $middleware_correlation_id,
            header_name: HttpHeader::X_CORRELATION_ID,
            override_existing_header: true,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(true);
        $request->method('getHeaderLine')->with(HttpHeader::X_CORRELATION_ID)->willReturn($request_correlation_id);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('hasHeader')->with(HttpHeader::X_CORRELATION_ID)->willReturn(false);
        $response->expects($this->once())
            ->method('withHeader')
            ->with(HttpHeader::X_CORRELATION_ID, $middleware_correlation_id)
            ->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }
}
