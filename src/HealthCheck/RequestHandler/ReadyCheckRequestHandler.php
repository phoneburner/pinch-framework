<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpReasonPhrase;
use PhoneBurner\Pinch\Component\Http\Domain\HttpStatus;
use PhoneBurner\Pinch\Component\Http\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class ReadyCheckRequestHandler implements RequestHandlerInterface
{
    public const string DEFAULT_ENDPOINT = '/readyz';

    public function __construct(public readonly LoggerInterface $logger)
    {
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->debug('application ready check: OK');

        return new TextResponse(HttpReasonPhrase::OK, HttpStatus::OK, [
            HttpHeader::CONTENT_TYPE => ContentType::TEXT,
            HttpHeader::CACHE_CONTROL => 'no-store',
        ]);
    }
}
