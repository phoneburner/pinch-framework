<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\String\Ciphertext;
use PhoneBurner\Pinch\Component\Http\Exception\HttpSessionException;
use PhoneBurner\Pinch\Component\Http\Exception\SessionWriteFailure;
use PhoneBurner\Pinch\Component\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;

final class EncryptingSessionHandlerDecorator extends SessionHandlerDecorator
{
    public const string HKDF_CONTEXT = 'http-session-data';

    public function __construct(
        protected SessionHandler $handler,
        private readonly Natrium $natrium,
    ) {
    }

    /**
     * Decrypt the session data returned from the previous before returning it
     */
    #[\Override]
    public function read(string|SessionId $id): string
    {
        $data = $this->handler->read($id);
        return $data === '' ? '' : (string)$this->natrium->decrypt(new Ciphertext($data), self::HKDF_CONTEXT, $id);
    }

    /**
     * Encrypt the session data before writing it
     */
    #[\Override]
    public function write(string|SessionId $id, string $data): bool
    {
        try {
            return $this->handler->write($id, $this->natrium->encrypt($data, self::HKDF_CONTEXT, $id)->bytes());
        } catch (\Throwable $e) {
            throw $e instanceof HttpSessionException ? $e : new SessionWriteFailure(
                message: 'Failed to encrypt session data',
                previous: $e,
            );
        }
    }
}
