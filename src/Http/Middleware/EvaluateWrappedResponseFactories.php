<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Middleware;

use PhoneBurner\ApiHandler\TransformableResponse;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\ResponseException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * This middleware is responsible for evaluating wrapped responses (e.g. instances
 * of `Psr\Http\Message\ResponseInterface` using the `PhoneBurner\Http\Message\ResponseWrapper`
 * trait), executing the factory callback, if it has not already been executed.
 * This is necessary to ensure that the response is in a state where any errors
 * that might occur while evaluating the wrapped response can be caught and handled
 * within the context of the middleware stack, allowing for context and build
 * stage aware, "user-friendly" error handling. Otherwise, if the factory callback
 * is not evaluated until the emitter begins to emit the response, any errors have
 * to be treated as "uncaught", and it is not possible to reliably send a proper
 * error response to the client.
 *
 * Note: This middleware should be listed as early as possible in the middleware
 * queue, but after the `PhoneBurner\Pinch\Framework\Http\Middleware\TransformHttpExceptionResponses`
 * and `PhoneBurner\Pinch\Framework\Http\Middleware\CatchExceptionalResponses`
 * middlewares. (That is, since this middleware gets the response first, listing
 * it early really means that it acts late in the process.)
 *
 * @todo Right now, this middleware requires that any class that implements the
 *   trait is manually checked in the conditional. It would be nice if classes
 *   using the trait implemented a common interface that could be checked instead.
 */
class EvaluateWrappedResponseFactories implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response instanceof ResponseException || $response instanceof TransformableResponse) {
            $response->getWrapped();
        }

        return $response;
    }
}
