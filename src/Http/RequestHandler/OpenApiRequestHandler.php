<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\FileNotFoundResponse;
use PhoneBurner\Pinch\Component\Http\Response\StreamResponse;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Component\Http\Stream\FileStream;
use PhoneBurner\Pinch\Component\Http\Stream\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenApiRequestHandler implements RequestHandlerInterface
{
    /**
     * @param string|null $json_path set null to disable
     * @param string|null $html_path set null to disable
     * @param string|null $yaml_path set null to disable
     */
    public function __construct(
        private readonly string|null $json_path,
        private readonly string|null $html_path,
        private readonly string|null $yaml_path = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $content_type = $request->getAttribute(RouteMatch::class)?->getAttributes()[ContentType::class] ?? null;
        $content_type = match (true) {
            $content_type !== null => $content_type,
            Psr7::expects($request, ContentType::JSON) => ContentType::JSON,
            Psr7::expects($request, ContentType::YAML) => ContentType::YAML,
            default => ContentType::HTML
        };

        $file = match ($content_type) {
            ContentType::JSON => $this->json_path,
            ContentType::HTML => $this->html_path,
            ContentType::YAML => $this->yaml_path,
            default => null,
        };

        if ($file === null) {
            return new FileNotFoundResponse();
        }

        $stream = StreamFactory::file($file);
        if (! $stream instanceof FileStream) {
            return new FileNotFoundResponse();
        }

        return new StreamResponse($stream, headers: [
            HttpHeader::CONTENT_TYPE => $content_type,
            HttpHeader::CONTENT_LENGTH => $stream->getSize() ?? 0,
        ]);
    }
}
