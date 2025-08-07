<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\TestSupport;

use GuzzleHttp\Psr7\Uri;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;

class MockRequestBuilder
{
    private array $input = [];

    private array $query = [];

    private array $server = [];

    private array $cookie = [];

    private string $request_type = HttpMethod::Get->value;

    private string $uri = '';

    private array $attributes = [];

    public function __construct(private readonly MockObject&ServerRequestInterface $mock_server_request)
    {
    }

    public function withInput(array $input): self
    {
        $this->input = $input;

        return $this;
    }

    public function withQuery(array $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function withServer(array $server): self
    {
        $this->server = $server;
        return $this;
    }

    public function withCookie(array $cookie): self
    {
        $this->cookie = $cookie;
        return $this;
    }

    public function withRequestMethod(HttpMethod|string $type): self
    {
        $this->request_type = HttpMethod::instance($type)->value;

        return $this;
    }

    public function withUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function make(): ServerRequestInterface
    {
        $this->mock_server_request->method('getHeaderLine')
            ->with(HttpHeader::CONTENT_TYPE)
            ->willReturn('');

        $this->mock_server_request->method('getParsedBody')
            ->willReturn($this->input);

        // assuming body is json.  TODO: add support for form body
        $contents = \json_encode($this->input, \JSON_THROW_ON_ERROR);
        $this->mock_server_request->method('getBody')
            ->willReturn(Psr7::stream($contents));

        $this->mock_server_request->method('getQueryParams')
            ->willReturn($this->query);

        $this->mock_server_request->method('getServerParams')
            ->willReturn($this->server);

        $this->mock_server_request->method('getCookieParams')
            ->willReturn($this->cookie);

        $this->mock_server_request->method('getMethod')
            ->willReturn($this->request_type);

        $this->mock_server_request->method('getUri')
            ->willReturn(new Uri($this->uri));

        $this->mock_server_request
            ->method('getAttribute')
            ->withAnyParameters()
            ->willReturnCallback(fn($name, $default = null) => $this->attributes[$name] ?? $default);

        return $this->mock_server_request;
    }

//    public static function fromRequest(RequestInterface $request): Request
//    {
//        $body = (string)$request->getBody();
//        $request->getBody()->rewind();
//
//        $params = [];
//        \parse_str($body, $params);
//
//        $query = [];
//        \parse_str($request->getUri()->getQuery(), $query);
//
//        return new Request(new ServerRequest(
//            [],
//            [],
//            $request->getUri(),
//            $request->getMethod(),
//            $request->getBody(),
//            $request->getHeaders(),
//            [],
//            $query,
//            $params,
//        ));
//    }
}
