<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Middleware;

use PhoneBurner\Pinch\Component\Http\RequestHandlerFactory;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DispatchRouteRequestHandler implements MiddlewareInterface
{
    public function __construct(private readonly RequestHandlerFactory $request_handler_factory)
    {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute(RouteMatch::class);
        if ($route instanceof RouteMatch) {
            $handler = $this->request_handler_factory->make($route->getHandler());
        }

        return $handler->handle($request);
    }
}
