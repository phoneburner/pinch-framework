<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Middleware;

use PhoneBurner\Pinch\Component\Http\Cookie\CookieJar;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Session\SessionData;
use PhoneBurner\Pinch\Component\Http\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class EnableHttpSession implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionManager $session_manager,
        private readonly CookieJar $cookie_jar,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->session_manager->start($request);
        $request = $request->withAttribute(SessionData::class, $session);
//        $this->session_manager->session()->flash('thing', 'hello world');
        $response = $handler->handle($request);
        $this->session_manager->save();

        // Check the manager for any session-related cookies that need added to the CookieJar
        foreach ($this->session_manager->cookies() as $cookie) {
            $this->cookie_jar->add($cookie);
        }

        // Set Cache Control Header to Prevent Session Pages from Being Cached
        return $response->withHeader(HttpHeader::CACHE_CONTROL, 'no-cache');
    }
}
