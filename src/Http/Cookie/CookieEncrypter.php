<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Cookie;

use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\String\Ciphertext;
use PhoneBurner\Pinch\Component\Http\Cookie\Cookie;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\CookieSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\SessionManager;
use PhoneBurner\Pinch\String\Encoding\ConstantTimeEncoder;
use PhoneBurner\Pinch\String\Encoding\Encoding;

/**
 * When using encrypted cookies, one implementation detail that has to be addressed
 * is how to determine if a cookie is encrypted or not, since we have to expect that
 * not all cookies sent with the request will be encrypted. We don't want to try
 * to decrypt every cookie, since that would be a waste of resources and a performance
 * bottleneck. We also cannot assume that any string that matches a certain encoding
 * regex (like Base64) is encrypted. We also do not want to leak information about
 * the encrypted cookies, so we cannot use a prefix that is the same for all encrypted
 * cookies or that is predictable in any way, e.g. "pinch-encrypted-cookie.".
 *
 * We solve this problem by using taking a page out of the Laravel playbook and
 * add a prefix to the encrypted cookie value that is a HMAC of the cookie name.
 * Following our cryptographic best practices, we use a Blake2b keyed hash in
 * place of a true HMAC, and we derive separate keys for the prefix and the
 * cookie value from a seed key using HKDF-Blake2b. When we process a cookie, we
 * take the plaintext cookie name and generate the prefix using the prefix key.
 * If it matches the first 22 characters of the cookie value, can be almost 100%
 * certain that the cookie is one that we encrypted.
 *
 * Note that the prefix and ciphertext are concatenated together *after* they
 * have been encoded using Base64URLNoPadding. This is a bit unusual as we usually
 * prefix nonces and authentication tags before encoding. However, in this case,
 * we don't want to mix the bytes of the prefix and the ciphertext, since we need
 * to expect that many values won't decode to valid ciphertexts anyway.
 *
 * Additionally, note that since we are using an AEAD construction to encrypt the
 * cookie, we use the cookie name as the additional data parameter to the encryption.
 * This ensures that the cookie name is authenticated along with the ciphertext, and
 * that the ciphertext cannot be decrypted if the cookie name is tampered with, or
 * if the cookie value is changed with a different cookie value that was encrypted with the
 * same key.
 *
 * Even though we use a key-commiting, verify-then-decrypt AEAD construction and
 * are including the cookie name as the additional data, we still need to use a prefix
 * because we cannot otherwise tell the difference between the sad-path for an
 * encrypted cookie and the happy path for a non-encrypted cookie.
 */
class CookieEncrypter
{
    public const string HKDF_CONTEXT_COOKIE = 'http-cookie';
    public const string HKDF_CONTEXT_PREFIX = 'http-cookie-prefix';

    /**
     * Don't try to decrypt cookies with these names, since we already know they
     * are not encrypted or that we won't be able to decrypt them here (like with
     * the CookieSessionHandler "SESSION_DATA" cookie, which needs the Session ID
     * to construct the additional data input for decryption).
     */
    public const array IGNORED_COOKIE_NAMES = [
        'XDEBUG_SESSION',
        'XDEBUG_PROFILE',
        CookieSessionHandler::COOKIE_NAME,
        SessionManager::SESSION_ID_COOKIE_NAME,
        SessionManager::XSRF_COOKIE_NAME,
    ];

    /**
     * The 16 bytes for the prefix encode to 22 characters using Base64URLNoPadding
     * The 88 bytes of the minimum ciphertext encode 118 characters in Base64URLNoPadding
     */
    public const int MIN_ENCODED_CHARS = 140;
    public const int PREFIX_ENCODED_CHARS = 22;
    public const int PREFIX_BYTES = 16;

    public const Encoding ENCODING = Encoding::Base64UrlNoPadding;

    public function __construct(
        private readonly Natrium $natrium,
    ) {
    }

    public function encrypt(Cookie $cookie): Cookie
    {
        // We only need the first 16 bytes of the 512-bit message signature digest
        $prefix = $this->natrium->sign($cookie->name, self::HKDF_CONTEXT_PREFIX)
            ->first(self::PREFIX_BYTES)
            ->export(self::ENCODING);

        $ciphertext = $this->natrium->encrypt(
            $cookie->value,
            self::HKDF_CONTEXT_COOKIE,
            $cookie->name,
        )->export(self::ENCODING);

        // sanity checks that the prefix and ciphertext are the correct lengths
        \assert(\strlen($prefix) === self::PREFIX_ENCODED_CHARS);
        \assert(\strlen($ciphertext) >= self::MIN_ENCODED_CHARS - self::PREFIX_ENCODED_CHARS);

        return $cookie->withValue($prefix . $ciphertext);
    }

    public function decrypt(string $name, string $value): string|null
    {
        // Value is too short to be an encrypted cookie, return it as-is.
        if (\strlen($value) <= self::MIN_ENCODED_CHARS) {
            return $value;
        }

        // Skip any cookies we are intentionally ignoring
        if (\in_array($name, self::IGNORED_COOKIE_NAMES, true)) {
            return $value;
        }

       // Just return values that don't match the Base64URLNoPadding encoding regex
        if (! \preg_match(Encoding::BASE64URL_NO_PADDING_REGEX, $value)) {
            return $value;
        }

        // Compute the prefix MAC from the cookie name and use constant time comparison
        // to check if the prefix matches the first 22 characters of the cookie value.
        // If not, just return the value as-is, since it's not an encrypted cookie.
        $prefix = $this->natrium->sign($name, self::HKDF_CONTEXT_PREFIX)
            ->first(self::PREFIX_BYTES)
            ->export(self::ENCODING);

        if (! ConstantTimeEncoder::stringStartsWith($value, $prefix)) {
            return $value;
        }

        try {
            // If decryption fails, we don't want to return the original value,
            // since we're sure that this was supposed to be an encrypted cookie.
            // Something must have gone wrong -- let the caller decide what to do.
            return $this->natrium->decrypt(
                Ciphertext::import(\substr($value, self::PREFIX_ENCODED_CHARS), self::ENCODING),
                self::HKDF_CONTEXT_COOKIE,
                $name,
            );
        } catch (\Throwable) { // Decryption failed
            return null;
        }
    }
}
