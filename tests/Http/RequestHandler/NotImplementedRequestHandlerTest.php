<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Response\Exceptional\NotImplementedResponse;
use PhoneBurner\Pinch\Framework\Http\Event\NotImplementedRequestHandled;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\NotImplementedRequestHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

final class NotImplementedRequestHandlerTest extends TestCase
{
    #[Test]
    public function happyPath(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $event_dispatcher = $this->createMock(EventDispatcherInterface::class);
        $event_dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new NotImplementedRequestHandled($request));

        $handler = new NotImplementedRequestHandler($event_dispatcher);
        $response = $handler->handle($request);

        self::assertInstanceOf(NotImplementedResponse::class, $response);
    }
}
