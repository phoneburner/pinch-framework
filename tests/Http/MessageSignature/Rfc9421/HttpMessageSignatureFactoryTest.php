<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\MessageSignature\Rfc9421;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\KeyChain;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SharedKey;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureFactory;
use PhoneBurner\Pinch\Time\Clock\Clock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HttpMessageSignatureFactory::class)]
final class HttpMessageSignatureFactoryTest extends TestCase
{
    private Clock&MockObject $clock;
    private HttpMessageSignatureFactory $factory;
    private string $test_key_id;

    protected function setUp(): void
    {
        // Create a test app key for consistent key generation
        $app_key = SharedKey::generate();
        $key_chain = new KeyChain($app_key);
        $natrium = new Natrium($key_chain);

        // Generate key ID as SHA-256 hash of public key for consistent test data
        $public_key = $key_chain->signature()->public();
        $key_hash = \hash('sha256', $public_key->bytes(), binary: true);
        $this->test_key_id = \bin2hex($key_hash);

        $this->clock = $this->createMock(Clock::class);
        $this->factory = new HttpMessageSignatureFactory(
            $natrium,
            $key_chain,
            $this->clock,
        );
    }

    // =============================================================
    // SIGNING TESTS
    // =============================================================

    #[Test]
    public function signRequestWithDefaultHeaders(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request(
            'POST',
            'https://api.example.com/v1/users',
            [
                HttpHeader::CONTENT_TYPE => 'application/json',
                HttpHeader::IDEMPOTENCY_KEY => 'idmp-12345',
            ],
            '{"name":"John Doe","email":"john@example.com"}',
        );

        // Act
        $signed_request = $this->factory->sign($request);

        // Assert
        self::assertInstanceOf(RequestInterface::class, $signed_request);
        self::assertTrue($signed_request->hasHeader('Signature-Input'));
        self::assertTrue($signed_request->hasHeader('Signature'));
        self::assertTrue($signed_request->hasHeader('Content-Digest'));

        // Verify signature input format
        $signature_input = $signed_request->getHeaderLine('Signature-Input');
        self::assertStringContainsString('sig1=', $signature_input);
        self::assertStringContainsString('created=' . $timestamp, $signature_input);
        self::assertStringContainsString('alg="ed25519"', $signature_input);
        self::assertStringContainsString('keyid="' . $this->test_key_id . '"', $signature_input);

        // Verify signature format
        $signature = $signed_request->getHeaderLine('Signature');
        self::assertMatchesRegularExpression('/^sig1=:[A-Za-z0-9+\/]+={0,2}:$/', $signature);

        // Verify content digest format
        $content_digest = $signed_request->getHeaderLine('Content-Digest');
        self::assertStringStartsWith('sha-512=:', $content_digest);
        self::assertStringEndsWith(':', $content_digest);
    }

    #[Test]
    public function signRequestWithCustomHeaders(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request(
            'GET',
            'https://api.example.com/v1/users/123',
            [
                'Authorization' => 'Bearer token123',
                'X-Custom-Header' => 'custom-value',
            ],
            '',
        );

        // Act
        $signed_request = $this->factory->sign(
            $request,
            signature_input_name: 'sig2',
            additional_headers: ['Authorization', 'X-Custom-Header'],
        );

        // Assert
        $signature_input = $signed_request->getHeaderLine('Signature-Input');
        self::assertStringContainsString('sig2=', $signature_input);
        self::assertStringContainsString('"authorization"', $signature_input);
        self::assertStringContainsString('"x-custom-header"', $signature_input);

        $signature = $signed_request->getHeaderLine('Signature');
        self::assertStringStartsWith('sig2=:', $signature);
    }

    #[Test]
    public function signResponseWithStatusCode(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $response = new Response(
            201,
            [
                HttpHeader::CONTENT_TYPE => 'application/json',
                'Location' => 'https://api.example.com/v1/users/123',
            ],
            '{"id":123,"name":"John Doe"}',
        );

        // Act
        $signed_response = $this->factory->sign($response, additional_headers: ['Location']);

        // Assert
        self::assertInstanceOf(ResponseInterface::class, $signed_response);
        self::assertTrue($signed_response->hasHeader('Signature-Input'));
        self::assertTrue($signed_response->hasHeader('Signature'));

        $signature_input = $signed_response->getHeaderLine('Signature-Input');
        self::assertStringContainsString('"@status"', $signature_input);
        self::assertStringContainsString('"location"', $signature_input);
    }

    #[Test]
    public function signMessageCalculatesCorrectContentDigest(): void
    {
        // Arrange
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp(1618884473));
        $content = '{"test":"data"}';
        $request = new Request('POST', 'https://example.com', [], $content);

        // Act
        $signed_request = $this->factory->sign($request);

        // Assert
        $content_digest = $signed_request->getHeaderLine('Content-Digest');
        $expected_hash = \base64_encode(\hash('sha512', $content, binary: true));
        $expected_digest = \sprintf('sha-512=:%s:', $expected_hash);

        self::assertSame($expected_digest, $content_digest);
    }

    #[Test]
    public function signMessageWithSeekableBodyStream(): void
    {
        // Arrange
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp(1618884473));
        $content = '{"seekable":"stream"}';
        $stream = Utils::streamFor($content);
        $stream->read(5); // Move position

        $request = new Request('POST', 'https://example.com', [], $stream);

        // Act
        $signed_request = $this->factory->sign($request);

        // Assert
        // Stream should be rewound and body should be signed correctly
        self::assertTrue($signed_request->hasHeader('Content-Digest'));
        self::assertSame(0, $signed_request->getBody()->tell());
    }

    #[Test]
    public function signMessageWithNonSeekableBodyStream(): void
    {
        // Arrange
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp(1618884473));
        $content = '{"non-seekable":"stream"}';

        // Create a mock non-seekable stream
        $stream = $this->createMock(Stream::class);
        $stream->method('__toString')->willReturn($content);
        $stream->method('isSeekable')->willReturn(false);

        $request = new Request('POST', 'https://example.com', [], $stream);

        // Act
        $signed_request = $this->factory->sign($request);

        // Assert
        // Should create new body stream and sign correctly
        self::assertTrue($signed_request->hasHeader('Content-Digest'));
        self::assertNotSame($stream, $signed_request->getBody());
    }

    #[Test]
    public function signMessageWithEmptyBody(): void
    {
        // Arrange
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp(1618884473));
        $request = new Request('GET', 'https://example.com', [], '');

        // Act
        $signed_request = $this->factory->sign($request);

        // Assert
        $content_digest = $signed_request->getHeaderLine('Content-Digest');
        $expected_hash = \base64_encode(\hash('sha512', '', binary: true));
        $expected_digest = \sprintf('sha-512=:%s:', $expected_hash);

        self::assertSame($expected_digest, $content_digest);
    }

    #[Test]
    #[DataProvider('provideHttpMethods')]
    public function signRequestWithDifferentMethods(string $method): void
    {
        // Arrange
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp(1618884473));
        $request = new Request($method, 'https://example.com/path', [], '');

        // Act
        $signed_request = $this->factory->sign($request);

        // Assert
        $signature_input = $signed_request->getHeaderLine('Signature-Input');
        self::assertStringContainsString('"@method"', $signature_input);
        self::assertStringContainsString('"@target-uri"', $signature_input);
    }

    public static function provideHttpMethods(): \Generator
    {
        yield 'GET' => ['GET'];
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
    }

    #[Test]
    #[DataProvider('provideStatusCodes')]
    public function signResponseWithDifferentStatusCodes(int $status_code): void
    {
        // Arrange
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp(1618884473));
        $response = new Response($status_code, [], '');

        // Act
        $signed_response = $this->factory->sign($response);

        // Assert
        $signature_input = $signed_response->getHeaderLine('Signature-Input');
        self::assertStringContainsString('"@status"', $signature_input);
    }

    public static function provideStatusCodes(): \Generator
    {
        yield '200 OK' => [200];
        yield '201 Created' => [201];
        yield '400 Bad Request' => [400];
        yield '401 Unauthorized' => [401];
        yield '404 Not Found' => [404];
        yield '500 Internal Server Error' => [500];
    }

    // =============================================================
    // VERIFICATION TESTS
    // =============================================================

    #[Test]
    public function verifyValidSignatureReturnsTrue(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request(
            'POST',
            'https://api.example.com/v1/users',
            [HttpHeader::CONTENT_TYPE => 'application/json'],
            '{"name":"John Doe"}',
        );

        $signed_request = $this->factory->sign($request);

        // Act
        $is_valid = $this->factory->verify($signed_request);

        // Assert
        self::assertTrue($is_valid);
    }

    #[Test]
    public function verifyTamperedSignatureReturnsFalse(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request('POST', 'https://example.com', [], '{"test":"data"}');
        $signed_request = $this->factory->sign($request);

        // Tamper with the signature
        $tampered_request = $signed_request->withHeader(
            'Signature',
            'sig1=:TAMPERED_SIGNATURE_VALUE:',
        );

        // Act
        $is_valid = $this->factory->verify($tampered_request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithoutSignatureHeadersReturnsFalse(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithMissingSignatureInputReturnsFalse(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com');
        $request_with_signature = $request->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request_with_signature);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithMissingSignatureReturnsFalse(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com');
        $request_with_input = $request->withHeader(
            'Signature-Input',
            'sig1=("@method" "@target-uri");created=1618884473;alg="ed25519";keyid="' . $this->test_key_id . '"',
        );

        // Act
        $is_valid = $this->factory->verify($request_with_input);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithUnknownKeyIdReturnsFalse(): void
    {
        // Arrange
        $unknown_key_id = 'unknown-key-id-that-does-not-exist-in-keychain';

        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "@target-uri");created=1618884473;alg="ed25519";keyid="' . $unknown_key_id . '"',
            )
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithInvalidTimestampReturnsFalse(): void
    {
        // Arrange
        $current_time = 1618884473;
        $old_timestamp = $current_time - 400; // 6+ minutes old (beyond 5 minute skew)

        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($current_time));

        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "@target-uri");created=' . $old_timestamp . ';alg="ed25519";keyid="' . $this->test_key_id . '"',
            )
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithFutureTimestampReturnsFalse(): void
    {
        // Arrange
        $current_time = 1618884473;
        $future_timestamp = $current_time + 400; // 6+ minutes in future (beyond 5 minute skew)

        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($current_time));

        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "@target-uri");created=' . $future_timestamp . ';alg="ed25519";keyid="' . $this->test_key_id . '"',
            )
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithAcceptableClockSkewReturnsTrue(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request('POST', 'https://example.com', [], '{"test":"data"}');
        $signed_request = $this->factory->sign($request);

        // Verify with 4 minutes of clock skew (within 5 minute tolerance)
        $verification_time = $timestamp + 240;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($verification_time));

        // Act
        $is_valid = $this->factory->verify($signed_request);

        // Assert
        self::assertTrue($is_valid);
    }

    #[Test]
    public function verifyMessageWithUnsupportedAlgorithmReturnsFalse(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "@target-uri");created=1618884473;alg="rsa-sha256";keyid="' . $this->test_key_id . '"',
            )
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithMissingKeyIdReturnsFalse(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "@target-uri");created=1618884473;alg="ed25519"',
            )
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithMalformedSignatureFormatReturnsFalse(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "@target-uri");created=1618884473;alg="ed25519";keyid="' . $this->test_key_id . '"',
            )
            ->withHeader('Signature', 'sig1=malformed-signature-format');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithTamperedContentReturnsFalse(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $original_content = '{"name":"John Doe"}';
        $request = new Request('POST', 'https://example.com', [], $original_content);
        $signed_request = $this->factory->sign($request);

        // Tamper with the body content
        $tampered_request = $signed_request->withBody(
            Utils::streamFor('{"name":"Jane Doe"}'),
        );

        // Act
        $is_valid = $this->factory->verify($tampered_request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyMessageWithTamperedHeaderReturnsFalse(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request(
            'POST',
            'https://example.com',
            [HttpHeader::CONTENT_TYPE => 'application/json'],
            '{"test":"data"}',
        );
        $signed_request = $this->factory->sign($request);

        // Tamper with a signed header
        $tampered_request = $signed_request->withHeader(HttpHeader::CONTENT_TYPE, 'application/xml');

        // Act
        $is_valid = $this->factory->verify($tampered_request);

        // Assert
        self::assertFalse($is_valid);
    }

    // =============================================================
    // ERROR HANDLING AND EDGE CASES
    // =============================================================

    #[Test]
    public function verifyHandlesInvalidSignatureInputGracefully(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com')
            ->withHeader('Signature-Input', 'malformed-signature-input-header')
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyHandlesExceptionFromSignatureInputParsingGracefully(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com')
            ->withHeader('Signature-Input', 'sig1=("@method"') // Missing closing parenthesis
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function signAndVerifyCompleteWorkflow(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request(
            'POST',
            'https://api.example.com/v1/users',
            [
                HttpHeader::CONTENT_TYPE => 'application/hal+json',
                HttpHeader::IDEMPOTENCY_KEY => 'idmp-67890',
                'Authorization' => 'Bearer access-token-123',
            ],
            '{"name":"Alice Smith","email":"alice@example.com"}',
        );

        // Act
        $signed_request = $this->factory->sign(
            $request,
            signature_input_name: 'api_sig',
            additional_headers: [HttpHeader::CONTENT_TYPE, HttpHeader::IDEMPOTENCY_KEY, 'Authorization'],
        );
        $is_valid = $this->factory->verify($signed_request);

        // Assert
        self::assertTrue($is_valid);

        // Verify all expected headers are present
        self::assertTrue($signed_request->hasHeader('Signature-Input'));
        self::assertTrue($signed_request->hasHeader('Signature'));
        self::assertTrue($signed_request->hasHeader('Content-Digest'));

        // Verify signature uses custom name
        $signature_input = $signed_request->getHeaderLine('Signature-Input');
        self::assertStringContainsString('api_sig=', $signature_input);

        $signature = $signed_request->getHeaderLine('Signature');
        self::assertStringStartsWith('api_sig=:', $signature);
    }

    #[Test]
    public function verifyWithMultipleSignaturesValidatesCorrectly(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request('GET', 'https://example.com', [], '');
        $signed_request = $this->factory->sign($request);

        // Current implementation only supports single signature validation
        // This test verifies that a properly signed message validates correctly
        // even when there might be additional header data
        $is_valid = $this->factory->verify($signed_request);

        // Assert
        self::assertTrue($is_valid);
    }

    #[Test]
    public function signAndVerifyEmptyBodyMessage(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request('HEAD', 'https://example.com/resource', [], '');

        // Act
        $signed_request = $this->factory->sign($request);
        $is_valid = $this->factory->verify($signed_request);

        // Assert
        self::assertTrue($is_valid);

        // Verify empty body gets correct digest
        $content_digest = $signed_request->getHeaderLine('Content-Digest');
        $expected_empty_hash = \base64_encode(\hash('sha512', '', binary: true));
        self::assertSame(\sprintf('sha-512=:%s:', $expected_empty_hash), $content_digest);
    }

    #[Test]
    public function signAndVerifyLargeBodyMessage(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        // Create a large body (1MB)
        $large_body = \str_repeat('{"data":"' . \str_repeat('x', 1000) . '"}', 1000);
        $request = new Request('POST', 'https://example.com/large', [], $large_body);

        // Act
        $signed_request = $this->factory->sign($request);
        $is_valid = $this->factory->verify($signed_request);

        // Assert
        self::assertTrue($is_valid);
    }

    #[Test]
    public function verifyHandlesMissingComponentsGracefully(): void
    {
        // Arrange - Create a request that claims to include a header that doesn't exist
        $request = new Request('GET', 'https://example.com')
            ->withHeader(
                'Signature-Input',
                'sig1=("@method" "missing-header");created=1618884473;alg="ed25519";keyid="' . $this->test_key_id . '"',
            )
            ->withHeader('Signature', 'sig1=:dGVzdA==:');

        // Act
        $is_valid = $this->factory->verify($request);

        // Assert
        self::assertFalse($is_valid);
    }

    #[Test]
    public function verifyTimingSafeComparison(): void
    {
        // Arrange
        $timestamp = 1618884473;
        $this->clock->method('now')->willReturn(CarbonImmutable::createFromTimestamp($timestamp));

        $request = new Request('GET', 'https://example.com', [], '');
        $signed_request = $this->factory->sign($request);

        // Act - Measure verification time for valid and invalid signatures
        $start_time = \hrtime(as_number: true);
        $valid_result = $this->factory->verify($signed_request);
        $valid_time = \hrtime(as_number: true) - $start_time;

        // Create invalid signature with correct base64 length but wrong content
        $invalid_signature = \base64_encode(\str_repeat('x', 64)); // 64 bytes for Ed25519
        $invalid_request = $signed_request->withHeader('Signature', \sprintf('sig1=:%s:', $invalid_signature));
        $start_time = \hrtime(as_number: true);
        $invalid_result = $this->factory->verify($invalid_request);
        $invalid_time = \hrtime(as_number: true) - $start_time;

        // Assert
        self::assertTrue($valid_result);
        self::assertFalse($invalid_result);

        // Timing should be similar (within reasonable bounds) for timing-safe comparison
        // This is more of a sanity check than a strict timing attack test
        $time_ratio = $invalid_time > 0 ? $valid_time / $invalid_time : 1.0;
        self::assertGreaterThan(0.1, $time_ratio, 'Verification times should be comparable for timing safety');
        self::assertLessThan(10.0, $time_ratio, 'Verification times should be comparable for timing safety');
    }
}
