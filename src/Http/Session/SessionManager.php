<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session;

use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\String\Ciphertext;
use PhoneBurner\Pinch\Component\Http\Cookie\Cookie;
use PhoneBurner\Pinch\Component\Http\Cookie\SameSite;
use PhoneBurner\Pinch\Component\Http\Exception\SessionAlreadyStarted;
use PhoneBurner\Pinch\Component\Http\Exception\SessionNotStarted;
use PhoneBurner\Pinch\Component\Http\Session\CsrfToken;
use PhoneBurner\Pinch\Component\Http\Session\SessionData;
use PhoneBurner\Pinch\Component\Http\Session\SessionHandler as SessionHandlerContract;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Component\Http\Session\SessionManager as SessionManagerContract;
use PhoneBurner\Pinch\Framework\Http\Config\SessionConfigStruct;
use PhoneBurner\Pinch\String\Encoding\ConstantTimeEncoder;
use PhoneBurner\Pinch\String\Encoding\Encoding;
use PhoneBurner\Pinch\String\Serialization\Marshaller;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * The SessionManager is responsible for managing the session lifecycle, e.g.
 * starting, saving, regenerating, and destroying sessions.
 */
class SessionManager implements SessionManagerContract
{
    public const string HKDF_CONTEXT = 'http-session';

    public const Encoding ENCODING = Encoding::Base64UrlNoPadding;

    /**
     * @link https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#session-id-name-fingerprinting
     * The session cookie name is intentionally kept generic to avoid fingerprinting.
     */
    public const string SESSION_ID_COOKIE_NAME = 'session_id';

    public const string XSRF_COOKIE_NAME = 'XSRF-TOKEN'; // Used for X-XSRF-TOKEN header

    private SessionId|null $cookie_session_id = null;

    private CsrfToken|null $cookie_xsrf_token = null;

    private SessionId|null $session_id = null;

    private SessionData|null $session_data = null;

    public function __construct(
        private readonly SessionHandlerContract $session_handler,
        private readonly SessionConfigStruct $config,
        private readonly Natrium $natrium,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function start(ServerRequestInterface $request): SessionData
    {
        if ($this->started()) {
            throw new SessionAlreadyStarted();
        }

        $this->session_id = $this->resolveSessionId($request);
        $this->config->add_xsrf_token_cookie && $this->resolveXsrfToken($request) instanceof CsrfToken;
        $this->session_handler->open(name: self::SESSION_ID_COOKIE_NAME, id: $this->cookie_session_id);
        $this->session_data = $this->resolveSessionData($this->cookie_session_id);
        return $this->session_data;
    }

    public function save(): bool
    {
        try {
            $this->started() || throw new SessionNotStarted();
            $data = Marshaller::serialize($this->session_data->preserialize(), serializer: $this->config->serializer);
            return $this->session_handler->write($this->session_id, $data);
        } catch (\Throwable $e) {
            $this->logger?->error("Session Manager: Error writing/serializing session data", ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Clear the session data and regenerate the ID
     *
     * This should be called when "logging out" a user.
     *
     * We need to clear the existing session, as opposed to setting it to a new
     * instance because we expect consuming code to hold references to the specific
     * Session instance, e.g. as an attribute of the Request.
     *
     * Note: this method does require the session to be started, but we're relying
     * on that to be checked in the call to regenerate(). Consuming code should
     * always check started() before calling this method.
     *
     * @param bool $destroy_existing If true, the existing session record will be
     *  destroyed by the handler -- this does not affect the current session data
     * (which we are clearing anyway). This should probably always be true; however,
     * the option is exposed as that record around (temporarily) to prevent issues
     * with concurrent requests if the session is not locked or for debugging.
     */
    public function invalidate(bool $destroy_existing = true): SessionData
    {
        $this->session_data?->clear();
        return $this->regenerate($destroy_existing);
    }

    /**
     * Generate a new session ID for the existing session. This has the effect
     * of moving the data from one ID to another, if any data exists.
     *
     * This should be called whenever there is a change to the user's authentication
     * state, e.g. login, logout, or change of user permissions in order to prevent
     * session fixation attacks. This is called by the invalidate() method.
     *
     * @param bool $destroy_existing If true, the existing session record will be
     * destroyed by the handler -- this does not affect the current session data.
     * By default, we probably want to do this to prevent session fixation attacks,
     * but there could be cases where we want to keep that record around (temporarily)
     * to prevent issue with concurrent requests if the session is not locked or
     * for debugging.
     */
    public function regenerate(bool $destroy_existing = true): SessionData
    {
        $this->started() || throw new SessionNotStarted();
        if ($destroy_existing) {
            $this->session_handler->destroy($this->session_id);
        }

        $this->session_id = SessionId::generate();
        $this->cookie_session_id = null;
        return $this->session_data ?? throw new SessionNotStarted();
    }

    /**
     * @phpstan-assert SessionData $this->session_data
     */
    public function session(): SessionData
    {
        return $this->session_data ?? throw new SessionNotStarted();
    }

    /**
     * @phpstan-assert-if-true SessionId $this->session_id
     * @phpstan-assert-if-true SessionData $this->session_data
     */
    public function started(): bool
    {
        return $this->session_id instanceof SessionId && $this->session_data instanceof SessionData;
    }

    /**
     * array<Cookie>
     */
    public function cookies(): array
    {
        $cookies = [];
        if ($this->shouldAddSessionIdCookie()) {
            $cookies[] = new Cookie(
                name: self::SESSION_ID_COOKIE_NAME,
                value: $this->encryptSessionId(),
                ttl: null, // browser session cookie (session TTL is controlled by the session handler)
                http_only: true,
                same_site: SameSite::Lax,
                encrypt: false,
            );
        }

        if ($this->shouldAddXsrfTokenCookie()) {
            $cookies[] = new Cookie(
                name: self::XSRF_COOKIE_NAME,
                value: $this->encryptXsrfToken(),
                ttl: null, // browser session cookie (session TTL is controlled by the session handler)
                http_only: false, // This is used for XHR requests, so this has to be accessible via JS
                same_site: SameSite::Lax,
                encrypt: false, // special handling since this is a cookie-to-header value
            );
        }

        return $cookies;
    }

    private function shouldAddSessionIdCookie(): bool
    {
        return $this->cookie_session_id === null || ! ConstantTimeEncoder::equals(
            $this->session_id ?? throw new SessionNotStarted(),
            $this->cookie_session_id,
        );
    }

    /**
     * If the XSRF_TOKEN cookie is enabled by the configuration, we need to check
     * if the session data has a different CSRF token than the one in the cookie.
     * This will be the case if the session data was regenerated or if the session
     * was started without a cookie.
     */
    private function shouldAddXsrfTokenCookie(): bool
    {
        if ($this->config->add_xsrf_token_cookie === false) {
            return false;
        }

        return $this->cookie_xsrf_token === null || ! ConstantTimeEncoder::equals(
            $this->session_data?->csrf() ?? throw new SessionNotStarted(),
            $this->cookie_xsrf_token,
        );
    }

    private function resolveSessionData(SessionId|null $session_id): SessionData
    {
        if ($session_id === null) {
            return new SessionData();
        }

        try {
            $serialized = $this->session_handler->read($session_id);
            return $serialized ? Marshaller::deserialize($serialized) : new SessionData();
        } catch (\Throwable $e) {
            $this->logger?->error("Session Manager: Error reading/deserializing session data", ['exception' => $e]);
            return new SessionData();
        }
    }

    private function resolveSessionId(ServerRequestInterface $request): SessionId
    {
        $this->cookie_session_id = $this->decryptSessionId($request->getCookieParams()[self::SESSION_ID_COOKIE_NAME] ?? null);
        if ($this->cookie_session_id === null) {
            return SessionId::generate();
        }

        $this->logger?->debug("Session Manager: Existing Session Cookie Found {id}", ['session_id' => $this->cookie_session_id]);

        return $this->cookie_session_id;
    }

    private function resolveXsrfToken(ServerRequestInterface $request): CsrfToken|null
    {
        $xsrf_token = $request->getCookieParams()[self::XSRF_COOKIE_NAME] ?? null;
        if ($xsrf_token === null) {
            return null;
        }

        $this->cookie_xsrf_token = $this->decryptXsrfToken($xsrf_token);
        if ($this->cookie_xsrf_token === null) {
            return null;
        }

        $this->logger?->debug("Session Manager: Existing XSRF Token Cookie Found {token}", ['token' => $this->cookie_xsrf_token]);

        return $this->cookie_xsrf_token;
    }

    private function encryptSessionId(): string
    {
        $this->started() || throw new SessionNotStarted();
        return $this->natrium->encrypt($this->session_id, self::HKDF_CONTEXT)->export(self::ENCODING);
    }

    private function decryptSessionId(string|null $session_id): SessionId|null
    {
        $ciphertext = Ciphertext::tryImport($session_id, self::ENCODING);
        if ($ciphertext === null) {
            return null;
        }

        $plaintext = $this->natrium->decrypt($ciphertext, self::HKDF_CONTEXT);
        return $plaintext === null ? null : new SessionId($plaintext);
    }

    /**
     * We use the session ID as additional data for the CSRF token encryption, so that
     * only the session that generated the token can decrypt it.
     */
    private function encryptXsrfToken(): string
    {
        $this->started() || throw new SessionNotStarted();
        return $this->natrium->encrypt($this->session_data->csrf(), self::HKDF_CONTEXT, $this->session_id)
            ->export(self::ENCODING);
    }

    /**
     * This method is public, unlike the other similar methods, because we need to
     * decrypt the encrypted CSRF token when it is sent in the X-XSRF-TOKEN header
     * by something like Axios, as part of resolving and validating the CSRF token.
     */
    public function decryptXsrfToken(string|null $xsrf_token): CsrfToken|null
    {
        // This method is called before the session is completed started, but
        // after the session id is resolved, so we cannot use started() here.
        if ($this->session_id === null) {
            throw new SessionNotStarted();
        }

        $ciphertext = Ciphertext::tryImport($xsrf_token, self::ENCODING);
        if ($ciphertext === null) {
            return null;
        }

        $plaintext = $this->natrium->decrypt($ciphertext, self::HKDF_CONTEXT, $this->session_id);
        return $plaintext === null ? null : new CsrfToken($plaintext);
    }
}
