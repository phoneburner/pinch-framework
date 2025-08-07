<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Component\Http\Exception\HttpSessionException;
use PhoneBurner\Pinch\Component\Http\Exception\SessionWriteFailure;
use PhoneBurner\Pinch\Component\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\SessionHandlerDecorator;

class CompressingSessionHandlerDecorator extends SessionHandlerDecorator
{
    public function __construct(protected SessionHandler $handler)
    {
    }

    /**
     * Decompress the session data returned from the previous before returning it
     * Note that the data might not be valid compressed data, but we will just let
     * the empty string be returned in that case.
     */
    #[\Override]
    public function read(string|SessionId $id): string
    {
        $data = $this->handler->read($id);
        return $data === '' ? '' : (string)@\gzinflate($data);
    }

    /**
     * Compress the session data before writing it
     */
    #[\Override]
    public function write(string|SessionId $id, string $data): bool
    {
        try {
            return $this->handler->write($id, @\gzdeflate($data, 1) ?: throw new SessionWriteFailure(
                'Failed to compress session data with gzdeflate()',
            ));
        } catch (\Throwable $e) {
            throw $e instanceof HttpSessionException ? $e : new SessionWriteFailure(
                message: 'Failed to encode session data',
                previous: $e,
            );
        }
    }
}
