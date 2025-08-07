<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Middleware;

use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpStatus;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\ServerErrorResponse;
use PhoneBurner\Pinch\Component\Http\Response\StreamResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class CatchExceptionalResponses implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BuildStage $stage,
        private readonly Context $context,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            if ($e instanceof ResponseInterface) {
                return $e;
            }

            $this->logger->error('Caught Exception: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return ($this->stage === BuildStage::Development && $this->context === Context::Http)
                ? $this->whoops($e, $request)
                : new ServerErrorResponse();
        }
    }

    private function whoops(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $handler = match (true) {
            Psr7::expects($request, ContentType::JSON) => new JsonResponseHandler(),
            Psr7::expects($request, ContentType::HTML) => new PrettyPageHandler(),
            default => new PlainTextHandler($this->logger),
        };

        $whoops = new Run();
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);
        $whoops->pushHandler($handler);

        return new StreamResponse(
            Psr7::stream($whoops->handleException($e)),
            HttpStatus::INTERNAL_SERVER_ERROR,
            [HttpHeader::CONTENT_TYPE => $handler->contentType()],
        );
    }
}
