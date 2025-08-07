<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Routing\Middleware;

use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Response\EmptyResponse;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\MethodNotAllowedResponse;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Component\Http\Routing\Result\MethodNotAllowed;
use PhoneBurner\Pinch\Component\Http\Routing\Result\RouteFound;
use PhoneBurner\Pinch\Component\Http\Routing\Result\RouteNotFound;
use PhoneBurner\Pinch\Component\Http\Routing\Router;
use PhoneBurner\Pinch\Framework\Http\Routing\Middleware\AttachRouteToRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AttachRouteToRequestTest extends TestCase
{
    private MockObject $router;

    private MockObject&ServerRequestInterface $request;

    private MockObject&RequestHandlerInterface $next_handler;

    private MockObject&ResponseInterface $response;

    private AttachRouteToRequest $sut;

    #[\Override]
    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->next_handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);

        $this->router = $this->createMock(Router::class);

        $this->sut = new AttachRouteToRequest($this->router);
    }

    #[Test]
    #[DataProvider('providesMethodsOtherThanOptions')]
    public function processReturnsMethodNotAllowedResponseWhenMatchMethodIsNotAllowed(HttpMethod $method): void
    {
        $methods = [HttpMethod::Get, HttpMethod::Post];

        $this->router->method('resolveForRequest')
            ->with($this->request)
            ->willReturn(MethodNotAllowed::make(...$methods));

        $this->request->method('getMethod')->willReturn($method->value);

        $response = $this->sut->process($this->request, $this->next_handler);
        self::assertInstanceOf(MethodNotAllowedResponse::class, $response);
        self::assertSame($response->allowed_methods, [HttpMethod::Get, HttpMethod::Post]);
    }

    public static function providesMethodsOtherThanOptions(): \Generator
    {
        yield [HttpMethod::Get];
        yield [HttpMethod::Post];
        yield [HttpMethod::Put];
        yield [HttpMethod::Patch];
        yield [HttpMethod::Delete];
        yield [HttpMethod::Head];
        yield [HttpMethod::Connect];
        yield [HttpMethod::Trace];
    }

    #[Test]
    public function processReturnsEmptyResponseWhenOptionsIsMethod(): void
    {
        $methods = [HttpMethod::Get, HttpMethod::Post];

        $this->router->method('resolveForRequest')
            ->with($this->request)
            ->willReturn(MethodNotAllowed::make(...$methods));

        $this->request->method('getMethod')->willReturn(HttpMethod::Options->value);
        $this->request->method('getHeaderLine')
            ->with(HttpHeader::ACCESS_CONTROL_REQUEST_HEADERS)
            ->willReturn('Authorization, Cookie');
        $this->request->method('hasHeader')->with(HttpHeader::ORIGIN)->willReturn(false);

        $response = $this->sut->process($this->request, $this->next_handler);
        self::assertInstanceOf(EmptyResponse::class, $response);
        self::assertSame('OPTIONS, GET, POST', $response->getHeaderLine(HttpHeader::ALLOW));
    }

    #[Test]
    #[DataProvider('providesMethodsOtherThanOptions')]
    public function processReturnsD(HttpMethod $method): void
    {
        $methods = [HttpMethod::Get, HttpMethod::Post];

        $this->router->method('resolveForRequest')
            ->with($this->request)
            ->willReturn(MethodNotAllowed::make(...$methods));

        $this->request->method('getMethod')->willReturn($method->value);

        $response = $this->sut->process($this->request, $this->next_handler);
        self::assertInstanceOf(MethodNotAllowedResponse::class, $response);
        self::assertSame($response->allowed_methods, [HttpMethod::Get, HttpMethod::Post]);
    }

    #[Test]
    public function processAttachesRouteWhenMatchIsFound(): void
    {
        $route = RouteDefinition::get('/test');
        $result = RouteFound::make($route, ['path' => 'data']);

        $this->router->method('resolveForRequest')
            ->with($this->request)
            ->willReturn($result);

        $request_with_route = $this->createMock(ServerRequestInterface::class);
        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(RouteMatch::class, RouteMatch::make($route, ['path' => 'data']))
            ->willReturn($request_with_route);

        $this->next_handler->expects($this->once())
            ->method('handle')
            ->with($request_with_route)
            ->willReturn($this->response);

        $response = $this->sut->process($this->request, $this->next_handler);

        self::assertSame($this->response, $response);
    }

    #[Test]
    public function processPassesWhenMatchIsNotFound(): void
    {
        $this->router->method('resolveForRequest')
            ->with($this->request)
            ->willReturn(RouteNotFound::make());

        $this->next_handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $response = $this->sut->process($this->request, $this->next_handler);

        self::assertSame($this->response, $response);
    }
}
