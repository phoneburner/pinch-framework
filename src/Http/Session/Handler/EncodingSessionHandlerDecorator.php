<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Component\Http\Exception\HttpSessionException;
use PhoneBurner\Pinch\Component\Http\Exception\SessionWriteFailure;
use PhoneBurner\Pinch\Component\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\String\Encoding\ConstantTimeEncoder;
use PhoneBurner\Pinch\String\Encoding\Encoding;

final class EncodingSessionHandlerDecorator extends SessionHandlerDecorator
{
    public function __construct(
        protected SessionHandler $handler,
        private readonly Encoding $encoding,
    ) {
    }

    /**
     * Decrypt the session data returned from the previous before returning it
     */
    #[\Override]
    public function read(string|SessionId $id): string
    {
        $data = $this->handler->read($id);
        return $data === '' ? '' : ConstantTimeEncoder::decode($this->encoding, $data);
    }

    /**
     * Encrypt the session data before writing it
     */
    #[\Override]
    public function write(string|SessionId $id, string $data): bool
    {
        try {
            return $this->handler->write($id, ConstantTimeEncoder::encode($this->encoding, $data));
        } catch (\Throwable $e) {
            throw $e instanceof HttpSessionException ? $e : new SessionWriteFailure(
                message: 'Failed to encode session data',
                previous: $e,
            );
        }
    }
}
