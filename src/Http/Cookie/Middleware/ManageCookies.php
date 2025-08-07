<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Cookie\Middleware;

use PhoneBurner\Pinch\Component\Http\Cookie\CookieJar;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\ServerErrorResponse;
use PhoneBurner\Pinch\Framework\Http\Cookie\CookieEncrypter;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-7 Friendly Cookie Handling Middleware
 *
 * Does two things related to managing HTTP cookies:
 * 1) Decrypts any encrypted cookies attached to the request in the cookie-params
 *    Cookies that are not encrypted are left as-is.
 * 2) Attaches any cookies queued up in the CookieJar while processing the request
 *    to the response, encrypting the value where configured.
 */
class ManageCookies implements MiddlewareInterface
{
    public function __construct(
        private readonly CookieJar $cookie_jar,
        private readonly CookieEncrypter $cookie_encrypter,
        private readonly Clock $clock,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Attach the cookie jar to the request so that it can be accessed later
        // by consuming code without needing to get it from the container. The
        // cookie jar is a quasi-singleton, so this should be safe to do.
        $request = $request->withAttribute(CookieJar::class, $this->cookie_jar);

        // Fully resolve the response from the handler first, to get accurate
        // count of cookies that we need to attach to the mutated response.
        $response = $handler->handle($this->mutateRequest($request));

        // Check if we queued up any cookies to attach to the response while processing
        // the request. If so, mutate the response to attach them
        return $this->mutateResponse($response);
    }

    private function mutateRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        // If there are no cookies, there is nothing to do, return the request as-is
        if ($request->getCookieParams() === []) {
            return $request;
        }

        $cookies = [];
        foreach ($request->getCookieParams() as $name => $value) {
            $value = $this->cookie_encrypter->decrypt($name, $value);
            // If decryption failed, unset the cookie and do not include it in the request
            if ($value === null) {
                $this->cookie_jar->remove($name);
                continue;
            }

            $cookies[$name] = $value;
        }

        return $request->withCookieParams($cookies);
    }

    private function mutateResponse(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->cookie_jar as $cookie) {
            $cookie_header = (match ($cookie->encrypt) {
                true => $this->cookie_encrypter->encrypt($cookie),
                default => $cookie,
            })->toString($this->clock);

            if (\strlen((string)$cookie_header) > 4096) {
                throw new ServerErrorResponse(detail: 'Cookie Exceeds 4kb Limit');
            }

            $response = $response->withAddedHeader(HttpHeader::SET_COOKIE, $cookie_header);
        }

        return $response;
    }
}
