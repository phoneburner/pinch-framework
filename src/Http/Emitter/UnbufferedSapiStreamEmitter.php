<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Emitter;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitterTrait;
use PhoneBurner\Pinch\Component\Http\Response\ServerSentEventsResponse;
use PhoneBurner\Pinch\Time\TimeInterval\TimeInterval;
use Psr\Http\Message\ResponseInterface;

class UnbufferedSapiStreamEmitter implements EmitterInterface
{
    use SapiEmitterTrait;

    public const int DEFAULT_BUFFER_SIZE = 8192;

    public function emit(ResponseInterface $response): bool
    {
        // Reset the time limit so the runtime does not terminate early
        if ($response instanceof ServerSentEventsResponse) {
            \set_time_limit($response->ttl->seconds === TimeInterval::max()->seconds
                ? 0
                : $response->ttl->seconds);
        }

        $this->assertNoPreviousOutput();
        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        \flush();

        $body = $response->getBody();
        while (! $body->eof()) {
            echo $body->read(self::DEFAULT_BUFFER_SIZE);
            \ob_flush();
            \flush();
        }

        return true;
    }
}
