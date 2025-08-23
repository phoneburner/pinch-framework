<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Handler;

use PhoneBurner\ApiHandler\ResponseFactory;
use PhoneBurner\ApiHandler\TransformableResource;
use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpReasonPhrase;
use PhoneBurner\Pinch\Component\Http\Domain\HttpStatus;
use PhoneBurner\Pinch\Component\Http\Response\EmptyResponse;
use PhoneBurner\Pinch\Component\Http\Response\JsonResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ApiResponseFactory implements ResponseFactoryInterface, ResponseFactory
{
    #[\Override]
    public function createResponse(
        int $code = HttpStatus::OK,
        string $reasonPhrase = HttpReasonPhrase::OK,
        mixed $data = [],
    ): ResponseInterface {
        $response = new JsonResponse($data, $code, [
            HttpHeader::CONTENT_TYPE => ContentType::HAL_JSON,
        ]);

        if ($response->getReasonPhrase() !== $reasonPhrase) {
            return $response->withStatus($code, $reasonPhrase);
        }

        return $response;
    }

    #[\Override]
    public function make(TransformableResource|null $resource = null, int $code = HttpStatus::OK): ResponseInterface
    {
        $content = $resource?->getContent();

        return $content
            ? $this->createResponse($code, HttpReasonPhrase::lookup($code), $content)
            : new EmptyResponse($code);
    }
}
