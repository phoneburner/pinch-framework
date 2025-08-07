<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Component\Http\Cookie\Cookie;
use PhoneBurner\Pinch\Component\Http\Cookie\CookieJar;
use PhoneBurner\Pinch\Component\Http\Cookie\SameSite;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\SessionManager;
use PhoneBurner\Pinch\Time\TimeInterval\TimeInterval;
use Psr\Http\Message\ServerRequestInterface;

class CookieSessionHandler extends SessionHandler
{
    public const string COOKIE_NAME = 'session_data';

    private string|null $session_data = null;

    public function __construct(
        private readonly CookieJar $cookie_jar,
        private readonly TimeInterval $ttl,
    ) {
    }

    #[\Override]
    public function open(
        string $path = '',
        string $name = SessionManager::SESSION_ID_COOKIE_NAME,
        SessionId|null $id = null,
        ServerRequestInterface|null $request = null,
    ): bool {
        $this->session_data = $request?->getCookieParams()[self::COOKIE_NAME] ?? null;
        return true;
    }

    public function read(string|SessionId $id): string
    {
        return (string)$this->session_data;
    }

    /**
     * Note: we have to send the cookie every time, even if the session data does
     * not change in order to keep the session alive, and update the max-age of the
     * cookie. (Just one reason that the other drivers are usually a better fit
     * for long term solutions.)
     */
    public function write(string|SessionId $id, string $data): bool
    {
        $this->cookie_jar->add(new Cookie(
            name: self::COOKIE_NAME,
            value: $data,
            ttl: $this->ttl,
            http_only: true,
            same_site: SameSite::Lax,
            encrypt: false, // already encrypted via the EncryptingSessionHandlerDecorator
        ));

        return true;
    }

    public function destroy(string|SessionId $id): bool
    {
        $this->cookie_jar->remove(self::COOKIE_NAME);
        return true;
    }
}
