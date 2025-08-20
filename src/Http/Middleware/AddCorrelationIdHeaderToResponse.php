<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Middleware;

use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * By default, this middleware should be configured to add the value of the
 * LogTrace instance as the X-Correlation-ID response header. It also needs to
 * be configured to run early in the middleware queue so that transformed error
 * responses will have the X-Correlation-ID header set.
 */
class AddCorrelationIdHeaderToResponse implements MiddlewareInterface
{
    public function __construct(
        private readonly \Stringable|string $correlation_id,
        private readonly string $header_name = HttpHeader::X_CORRELATION_ID,
        private readonly bool $override_existing_header = false,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (! $this->override_existing_header) {
            if ($response->hasHeader($this->header_name)) {
                return $response;
            }

            if ($request->hasHeader($this->header_name)) {
                return $response->withHeader($this->header_name, $request->getHeaderLine($this->header_name));
            }
        }

        return $response->withHeader($this->header_name, (string)$this->correlation_id);
    }
}
