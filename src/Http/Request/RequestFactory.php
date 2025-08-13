<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Request;

use Laminas\Diactoros\Request;
use Laminas\Diactoros\ServerRequestFactory;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Request\DefaultRequestFactory;
use PhoneBurner\Pinch\Component\Http\Request\RequestFactory as RequestFactoryContract;
use PhoneBurner\Pinch\Component\Http\Stream\TemporaryStream;
use PhoneBurner\Pinch\Component\IpAddress\IpAddress;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class RequestFactory implements RequestFactoryContract
{
    public function fromGlobals(): ServerRequestInterface
    {
        return ServerRequestFactory::fromGlobals()
            ->withAttribute(IpAddress::class, IpAddress::marshall($_SERVER));
    }

    /**
     * @param array<string, string|array<string>> $headers
     */
    public function createRequest(
        HttpMethod|string $method,
        mixed $uri,
        array $headers = [],
        StreamInterface $body = new TemporaryStream(),
    ): Request {
        return new DefaultRequestFactory()->createRequest($method, $uri, $headers, $body,);
    }

    /**
     * @param array<mixed> $serverParams
     */
    public function createServerRequest(
        HttpMethod|string $method,
        mixed $uri,
        array $serverParams = [],
        StreamInterface $body = new TemporaryStream(),
        array $headers = [],
        array $query = [],
        array $cookies = [],
        array $files = [],
        array|object|null $parsed = null,
        string $protocol = '1.1',
        array $attributes = [],
    ): ServerRequestInterface {
        return new DefaultRequestFactory()->createServerRequest(
            $method,
            $uri,
            $serverParams,
            $body,
            $headers,
            $query,
            $cookies,
            $files,
            $parsed,
            $protocol,
            $attributes,
        );
    }
}
