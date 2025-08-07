<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Cookie;

use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\KeyChain;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\String\MessageSignature;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SharedKey;
use PhoneBurner\Pinch\Component\Http\Cookie\Cookie;
use PhoneBurner\Pinch\Framework\Http\Cookie\CookieEncrypter;
use PhoneBurner\Pinch\Framework\Http\Session\SessionManager;
use PhoneBurner\Pinch\Uuid\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CookieEncrypterTest extends TestCase
{
    public const string KEY = 'pP8fF46Eb737WAN9ccW1iZJP3w/7GESMKgfWT38/aU0=';
    public const string COOKIE_NAME = 'test_cookie_name';
    public const string COOKIE_VALUE = '31b85f9d-5901-45e0-b798-05d101383c58';
    public const string ENCRYPTED_COOKIE = <<<EOL
        2Cvx_rZHp0MMuKJ2q1OJpQON8IxEYD2bKoaxd8-KY_U9cRus8DqvixoeQhaWBf-I4eCNaF21ikpdqSsGpP7IFL3UzOxnVG7g0i6iwjm6DekpEYNQhdsvsFJBRYGJ0vBfgcD_geWb839qDS_72Vyry-uUX-4Q
        EOL;

    #[Test]
    public function happyPath(): void
    {
        $cookie = new Cookie(self::COOKIE_NAME, self::COOKIE_VALUE);

        $seed_key = SharedKey::import(self::KEY);
        $sut = new CookieEncrypter(new Natrium(new KeyChain($seed_key)));

        $encrypted_cookie = $sut->encrypt($cookie);

        // The first 22 characters of the encrypted cookie value are the HMAC of
        // the cookie name, and do not change between encryption runs. This could
        // be place where adding additional information, like the user ID, as
        self::assertSame(
            \substr(self::ENCRYPTED_COOKIE, 0, CookieEncrypter::PREFIX_ENCODED_CHARS),
            \substr($encrypted_cookie->value(), 0, CookieEncrypter::PREFIX_ENCODED_CHARS),
        );

        // But the rest of the cookie value will be different every time.
        self::assertNotSame(self::ENCRYPTED_COOKIE, $encrypted_cookie->value);

        // New cookie object with the same name, but the value is encrypted
        self::assertNotSame($cookie, $encrypted_cookie);
        self::assertSame($cookie->name, $encrypted_cookie->name);
        self::assertNotSame($cookie->value, $encrypted_cookie->value);

        // Decrypt the encrypted cookie and verify that the value is the same
        self::assertSame(self::COOKIE_VALUE, $sut->decrypt($encrypted_cookie->name, $encrypted_cookie->value()));

        // We should be able to do the same with the known encrypted cookie
        self::assertSame(self::COOKIE_VALUE, $sut->decrypt(self::COOKIE_NAME, self::ENCRYPTED_COOKIE));
    }

    #[Test]
    public function happyPathMismatchKey(): void
    {
        $cookie = new Cookie(self::COOKIE_NAME, self::COOKIE_VALUE);

        $seed_key = SharedKey::generate();
        $sut = new CookieEncrypter(new Natrium(new KeyChain($seed_key)));

        $decrypted_value = $sut->decrypt($cookie->name, self::ENCRYPTED_COOKIE);

        self::assertSame(self::ENCRYPTED_COOKIE, $decrypted_value);
    }

    #[Test]
    public function happyPathTooShortToDecrypt(): void
    {
        $cookie = new Cookie('test_cookie_name', Uuid::random()->toString());

        $sut = new CookieEncrypter(new Natrium(new KeyChain(SharedKey::generate())));

        $decrypted_value = $sut->decrypt($cookie->name, $cookie->value());

        self::assertSame($cookie->value, $decrypted_value);
    }

    #[Test]
    public function happyPathSkippedKey(): void
    {
        $seed_key = SharedKey::import(self::KEY);
        $sut = new CookieEncrypter(new Natrium(new KeyChain($seed_key)));

        self::assertSame(self::ENCRYPTED_COOKIE, $sut->decrypt(
            SessionManager::SESSION_ID_COOKIE_NAME,
            self::ENCRYPTED_COOKIE,
        ));
    }

    #[Test]
    public function happyPathNotBase64Encoded(): void
    {
        $cookie = new Cookie(self::COOKIE_NAME, <<<EOL
            v4.public.eyJmb28iOjQyLCJiYXIiOiJiYXoiLCJxdXoiOlsicXV4Il19vJDkR3LoXgiOhtHEsk83hgG_tVZJUWaxYGLP32WNx-vW8udavDQ-RY8NS4Gjt7Q5JHz7JBXWE14dAm0ureUgCA.eyJrZXlfaWQiOiJmb29iYXJiYXoifQ
            EOL,);

        $seed_key = SharedKey::generate();
        $sut = new CookieEncrypter(new Natrium(new KeyChain($seed_key)));

        $decrypted_value = $sut->decrypt($cookie->name, $cookie->value());

        self::assertSame($cookie->value, $decrypted_value);
    }

    #[Test]
    public function sadPath(): void
    {
        $seed_key = SharedKey::import(self::KEY);
        $sut = new CookieEncrypter(new Natrium(new KeyChain($seed_key)));
        $bad_value = \substr_replace(self::ENCRYPTED_COOKIE, 'A', -1);

        self::assertNull($sut->decrypt(self::COOKIE_NAME, $bad_value));
    }

    #[Test]
    public function sadPathWithThrownException(): void
    {
        $natrium = $this->createMock(Natrium::class);
        $natrium->method('sign')->willReturn(MessageSignature::import(<<<'EOF'
            2Cvx_rZHp0MMuKJ2q1OJpWB5uEP5AyIjqsnVGAl1-GeM6U45y5wdAf8mxT-1yons5jtXXxcXKQIrQ4nEUgUSWQ==
            EOF));
        $natrium->method('decrypt')->willThrowException(new \Exception('Test exception'));

        $sut = new CookieEncrypter($natrium);

        self::assertNull($sut->decrypt(self::COOKIE_NAME, self::ENCRYPTED_COOKIE));
    }
}
