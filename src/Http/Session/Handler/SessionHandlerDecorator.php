<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Component\Http\Session\SessionHandler as SessionHandlerContract;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;

abstract class SessionHandlerDecorator extends SessionHandler
{
    // phpcs:ignore
    abstract protected SessionHandlerContract $handler { get; }

    #[\Override]
    public function open(
        string $path = '',
        string $name = SessionManager::SESSION_ID_COOKIE_NAME,
        SessionId|null $id = null,
        ServerRequestInterface|null $request = null,
    ): bool {
        return $this->handler->open($path, $name);
    }

    #[\Override]
    public function close(): bool
    {
        return $this->handler->close();
    }

    public function read(string|SessionId $id): string
    {
        return $this->handler->read($id);
    }

    public function write(string|SessionId $id, string $data): bool
    {
        return $this->handler->write($id, $data);
    }

    public function destroy(string|SessionId $id): bool
    {
        return $this->handler->destroy($id);
    }

    #[\Override]
    public function gc(int $max_lifetime): int
    {
        return $this->handler->gc($max_lifetime);
    }
}
