<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureBase;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureComponents;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpMessageSignatureBase::class)]
final class HttpMessageSignatureBaseTest extends TestCase
{
    #[Test]
    public function constructorBuildsSignatureBaseFromComponents(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method', '@target-uri'], ['created' => 1618884473]);
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'GET',
            '@target-uri' => 'https://example.com/test',
        ]);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);

        $expected = '"@method": GET' . "\n" .
                   '"@target-uri": https://example.com/test' . "\n" .
                   '"@signature-params": ("@method" "@target-uri");created=1618884473';

        self::assertSame($expected, $signature_base->signatureBase());
    }

    #[Test]
    public function fromComponentsFactoryMethod(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method']);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'POST']);

        $signature_base = HttpMessageSignatureBase::fromComponents($signature_input, $signature_components);

        self::assertInstanceOf(HttpMessageSignatureBase::class, $signature_base);
        self::assertStringContainsString('"@method": POST', $signature_base->signatureBase());
    }

    #[Test]
    public function constructorThrowsExceptionWhenComponentIsMissing(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method', 'content-digest']);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']); // Missing content-digest

        $this->expectException(InvalidHttpMessageSignatureInput::class);

        new HttpMessageSignatureBase($signature_input, $signature_components);
    }

    #[Test]
    #[DataProvider('provideRfc9421Examples')]
    public function signatureBaseFollowsRfc9421Format(
        array $covered_components,
        array $components,
        array $parameters,
        string $expected_signature_base,
    ): void {
        $signature_input = new HttpMessageSignatureInput('sig1', $covered_components, $parameters);
        $signature_components = new HttpMessageSignatureComponents($components);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);

        self::assertSame($expected_signature_base, $signature_base->signatureBase());
    }

    public static function provideRfc9421Examples(): \Generator
    {
        yield 'basic GET request' => [
            ['@method', '@target-uri'],
            ['@method' => 'GET', '@target-uri' => 'https://example.com/foo'],
            [],
            '"@method": GET' . "\n" .
            '"@target-uri": https://example.com/foo' . "\n" .
            '"@signature-params": ("@method" "@target-uri")',
        ];

        yield 'POST with headers and timestamp' => [
            ['@method', '@target-uri', 'content-digest', 'content-type'],
            [
                '@method' => 'POST',
                '@target-uri' => 'https://example.com/foo',
                'content-digest' => 'sha-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=',
                'content-type' => 'application/json',
            ],
            ['created' => 1618884473],
            '"@method": POST' . "\n" .
            '"@target-uri": https://example.com/foo' . "\n" .
            '"content-digest": sha-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=' . "\n" .
            '"content-type": application/json' . "\n" .
            '"@signature-params": ("@method" "@target-uri" "content-digest" "content-type");created=1618884473',
        ];

        yield 'with multiple parameters' => [
            ['@method', '@target-uri'],
            ['@method' => 'GET', '@target-uri' => '/foo'],
            ['created' => 1618884473, 'alg' => 'ed25519', 'keyid' => 'test-key-ed25519'],
            '"@method": GET' . "\n" .
            '"@target-uri": /foo' . "\n" .
            '"@signature-params": ("@method" "@target-uri");created=1618884473;alg="ed25519";keyid="test-key-ed25519"',
        ];
    }

    #[Test]
    #[DataProvider('provideComponentCanonicalizationCases')]
    public function signatureBasePerformsProperComponentCanonicalization(
        string $component_name,
        string $input_value,
        string $expected_canonical_value,
    ): void {
        $signature_input = new HttpMessageSignatureInput('sig1', [$component_name]);
        $signature_components = new HttpMessageSignatureComponents([$component_name => $input_value]);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);
        $result = $signature_base->signatureBase();

        self::assertStringContainsString(\sprintf('"%s": %s', \strtolower($component_name), $expected_canonical_value), $result);
    }

    public static function provideComponentCanonicalizationCases(): \Generator
    {
        yield '@method lowercase to uppercase' => ['@method', 'get', 'GET'];
        yield '@method already uppercase' => ['@method', 'POST', 'POST'];
        yield '@target-uri preserved' => ['@target-uri', 'https://Example.Com/Path', 'https://Example.Com/Path'];
        yield '@status preserved' => ['@status', '200', '200'];
        yield '@authority lowercase' => ['@authority', 'Example.Com', 'example.com'];
        yield '@scheme lowercase' => ['@scheme', 'HTTPS', 'https'];
        yield '@path preserved' => ['@path', '/Path/To/Resource', '/Path/To/Resource'];
        yield '@query preserved' => ['@query', 'param=Value&other=Test', 'param=Value&other=Test'];
    }

    #[Test]
    #[DataProvider('provideHeaderValueNormalizationCases')]
    public function signatureBaseNormalizesHeaderValues(string $input_value, string $expected_normalized): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['content-type']);
        $signature_components = new HttpMessageSignatureComponents(['content-type' => $input_value]);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);
        $result = $signature_base->signatureBase();

        self::assertStringContainsString(\sprintf('"content-type": %s', $expected_normalized), $result);
    }

    public static function provideHeaderValueNormalizationCases(): \Generator
    {
        yield 'trim whitespace' => ['  application/json  ', 'application/json'];
        yield 'collapse multiple spaces' => ['application/json;   charset=utf-8', 'application/json; charset=utf-8'];
        yield 'normalize tabs and spaces' => ["application/json;\tcharset=utf-8", 'application/json; charset=utf-8'];
        yield 'already normalized' => ['application/json; charset=utf-8', 'application/json; charset=utf-8'];
    }

    #[Test]
    public function signatureParametersLineIsCorrectlyFormatted(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method'], [
            'created' => 1618884473,
            'alg' => 'ed25519',
            'keyid' => 'test-key',
        ]);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);
        $result = $signature_base->signatureBase();

        // Extract the signature-params line
        $lines = \explode("\n", $result);
        $signature_params_line = \end($lines);

        self::assertStringStartsWith('"@signature-params": ("@method")', $signature_params_line);
        self::assertStringContainsString('created=1618884473', $signature_params_line);
        self::assertStringContainsString('alg="ed25519"', $signature_params_line);
        self::assertStringContainsString('keyid="test-key"', $signature_params_line);
    }

    #[Test]
    public function signatureParametersLineWithoutParameters(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method']);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);
        $result = $signature_base->signatureBase();

        $lines = \explode("\n", $result);
        $signature_params_line = \end($lines);

        self::assertSame('"@signature-params": ("@method")', $signature_params_line);
    }

    #[Test]
    public function componentOrderingPreservedFromSignatureInput(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@target-uri', '@method', 'content-type']);
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'POST',
            '@target-uri' => '/api/test',
            'content-type' => 'application/json',
        ]);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);
        $result = $signature_base->signatureBase();

        $lines = \explode("\n", $result);

        // Verify order matches signature input order, not component insertion order
        self::assertStringStartsWith('"@target-uri":', $lines[0]);
        self::assertStringStartsWith('"@method":', $lines[1]);
        self::assertStringStartsWith('"content-type":', $lines[2]);
        self::assertStringStartsWith('"@signature-params":', $lines[3]);
    }

    #[Test]
    public function toStringReturnsSameAsSignatureBase(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method']);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);

        self::assertSame($signature_base->signatureBase(), $signature_base->toString());
    }

    #[Test]
    public function stringableInterface(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method']);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);

        self::assertSame($signature_base->signatureBase(), (string)$signature_base);
    }

    #[Test]
    public function toBytesReturnsSameAsSignatureBase(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method']);
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);

        self::assertSame($signature_base->signatureBase(), $signature_base->toBytes());
    }

    #[Test]
    public function signatureBaseUsesLineFeedSeparators(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method', '@target-uri']);
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'GET',
            '@target-uri' => '/test',
        ]);

        $signature_base = new HttpMessageSignatureBase($signature_input, $signature_components);
        $result = $signature_base->signatureBase();

        // Verify uses LF (\n) not CRLF (\r\n)
        self::assertStringContainsString("\n", $result);
        self::assertStringNotContainsString("\r\n", $result);

        $lines = \explode("\n", $result);
        self::assertCount(3, $lines); // Two components + signature-params line
    }
}
