<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Middleware;

use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Exception\InvalidMiddlewareConfiguration;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\CsrfTokenRequiredResponse;
use PhoneBurner\Pinch\Component\Http\Session\CsrfToken;
use PhoneBurner\Pinch\Component\Http\Session\SessionData;
use PhoneBurner\Pinch\Component\Http\Session\SessionManager;
use PhoneBurner\Pinch\String\Encoding\ConstantTimeEncoder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class ValidateCsrfToken implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionManager $session_manager,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return match (HttpMethod::instance($request->getMethod())) {
            HttpMethod::Get, HttpMethod::Post, HttpMethod::Put, HttpMethod::Patch, HttpMethod::Delete => $this->handle($request, $handler),
            default => $handler->handle($request),
        };
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session_data = Psr7::attribute(SessionData::class, $request)
            ?: throw new InvalidMiddlewareConfiguration('CSRF Token Validation Requires Sessions to Be Enabled');

        $request_token = $this->extractCsrfToken($request);
        if ($request_token instanceof CsrfToken && ConstantTimeEncoder::equals($session_data->csrf(), $request_token)) {
            return $handler->handle($request);
        }

        // Fail closed if the CSRF token is invalid
        return new CsrfTokenRequiredResponse();
    }

    /**
     * We're taking a strict approach to CSRF tokens, if the token is present in
     * a header or form body, it must be valid. If it's not present, we'll continue
     * checking the other locations, but if it's invalid, we'll fail closed.
     */
    public function extractCsrfToken(ServerRequestInterface $request): CsrfToken|null
    {
        // Check the primary header for the encoded (but not encrypted) CSRF token
        $header_csrf_token = $request->getHeaderLine('X-CSRF-Token');
        if ($header_csrf_token) {
            return CsrfToken::tryImport($header_csrf_token);
        }

        // Check the header used by Axios and other JS libraries for the encrypted CSRF token
        $header_xsrf_token = $request->getHeaderLine('X-XSRF-Token');
        if ($header_xsrf_token) {
            return $this->session_manager->decryptXsrfToken($header_xsrf_token);
        }

        $form_body_token = ((array)$request->getParsedBody())['_token'] ?? null;
        if ($form_body_token) {
            return CsrfToken::tryImport($form_body_token);
        }

        $this->logger?->warning('CSRF Token not found in request');

        return null;
    }
}
