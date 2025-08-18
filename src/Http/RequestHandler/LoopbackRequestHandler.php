<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Response\EmptyResponse;
use PhoneBurner\Pinch\Framework\Http\Event\LoopbackRequestHandled;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoopbackRequestHandler implements RequestHandlerInterface
{
    /**
     * @param null|\Closure(): ResponseInterface $response_callback
     */
    public function __construct(
        private readonly EventDispatcherInterface $event_dispatcher,
        private readonly \Closure|null $response_callback = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->event_dispatcher->dispatch(new LoopbackRequestHandled($request));
        return $this->response_callback ? ($this->response_callback)() : new EmptyResponse();
    }
}
