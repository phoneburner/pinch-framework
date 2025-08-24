<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Response\Exceptional\NotImplementedResponse;
use PhoneBurner\Pinch\Framework\Http\Event\NotImplementedRequestHandled;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NotImplementedRequestHandler implements RequestHandlerInterface
{
    public function __construct(private readonly EventDispatcherInterface $event_dispatcher)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->event_dispatcher->dispatch(new NotImplementedRequestHandled($request));
        return new NotImplementedResponse();
    }
}
