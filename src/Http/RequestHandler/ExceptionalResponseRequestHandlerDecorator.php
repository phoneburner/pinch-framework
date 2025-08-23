<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RequestHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExceptionalResponseRequestHandlerDecorator implements RequestHandlerInterface
{
    public function __construct(private readonly RequestHandlerInterface $handler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->handler->handle($request);
        } catch (\Throwable $e) {
            return $e instanceof ResponseInterface ? $e : throw $e;
        }
    }
}
