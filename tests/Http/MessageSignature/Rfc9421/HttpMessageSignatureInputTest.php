<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpMessageSignatureInput::class)]
final class HttpMessageSignatureInputTest extends TestCase
{
    #[Test]
    public function constructorCreatesValidSignatureInput(): void
    {
        $signature_label = 'sig1';
        $covered_components = ['@method', '@target-uri', 'content-digest'];
        $parameters = ['created' => 1618884473, 'alg' => 'ed25519', 'keyid' => 'test-key-ed25519'];

        $signature_input = new HttpMessageSignatureInput($signature_label, $covered_components, $parameters);

        self::assertSame($signature_label, $signature_input->signatureLabel());
        self::assertSame($covered_components, $signature_input->coveredComponents());
        self::assertSame($parameters, $signature_input->parameters());
    }

    #[Test]
    public function constructorWithMinimalRequiredParameters(): void
    {
        $signature_label = 'sig1';
        $covered_components = ['@method', '@target-uri'];

        $signature_input = new HttpMessageSignatureInput($signature_label, $covered_components);

        self::assertSame($signature_label, $signature_input->signatureLabel());
        self::assertSame($covered_components, $signature_input->coveredComponents());
        self::assertSame([], $signature_input->parameters());
    }

    #[Test]
    #[DataProvider('provideInvalidSignatureLabels')]
    public function constructorThrowsExceptionForInvalidSignatureLabel(string $invalid_label): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        new HttpMessageSignatureInput($invalid_label, ['@method']);
    }

    public static function provideInvalidSignatureLabels(): \Generator
    {
        yield 'empty string' => [''];
        yield 'contains space' => ['sig 1'];
        yield 'contains hyphen' => ['sig-1'];
        yield 'contains special chars' => ['sig1!'];
        yield 'contains period' => ['sig1.test'];
    }

    #[Test]
    public function constructorThrowsExceptionForEmptyComponentsList(): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);
        $this->expectExceptionMessage('Covered components list cannot be empty');

        new HttpMessageSignatureInput('sig1', []);
    }

    #[Test]
    #[DataProvider('provideInvalidCoveredComponents')]
    public function constructorThrowsExceptionForInvalidCoveredComponents(array $invalid_components): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        new HttpMessageSignatureInput('sig1', $invalid_components);
    }

    public static function provideInvalidCoveredComponents(): \Generator
    {
        yield 'contains empty string' => [['@method', '']];
        yield 'contains non-string' => [['@method', 123]];
        yield 'contains null' => [['@method', null]];
    }

    #[Test]
    #[DataProvider('provideInvalidParameters')]
    public function constructorThrowsExceptionForInvalidParameters(array $invalid_parameters): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        new HttpMessageSignatureInput('sig1', ['@method'], $invalid_parameters);
    }

    public static function provideInvalidParameters(): \Generator
    {
        yield 'empty parameter key' => [['' => 'value']];
        yield 'non-string parameter key' => [[123 => 'value']];
        yield 'invalid parameter value type' => [['created' => []]];
        yield 'negative timestamp' => [['created' => -1]];
        yield 'empty nonce' => [['nonce' => '']];
        yield 'empty algorithm' => [['alg' => '']];
        yield 'empty keyid' => [['keyid' => '']];
    }

    #[Test]
    #[DataProvider('provideValidSignatureInputHeaders')]
    public function fromStringParsesValidHeaders(
        string $header,
        string $expected_label,
        array $expected_components,
        array $expected_parameters,
    ): void {
        $signature_input = HttpMessageSignatureInput::fromString($header);

        self::assertSame($expected_label, $signature_input->signatureLabel());
        self::assertSame($expected_components, $signature_input->coveredComponents());
        self::assertSame($expected_parameters, $signature_input->parameters());
    }

    public static function provideValidSignatureInputHeaders(): \Generator
    {
        yield 'basic with no parameters' => [
            'sig1=("@method" "@target-uri")',
            'sig1',
            ['@method', '@target-uri'],
            [],
        ];

        yield 'with string parameters' => [
            'sig1=("@method" "@target-uri");alg="ed25519";keyid="test-key"',
            'sig1',
            ['@method', '@target-uri'],
            ['alg' => 'ed25519', 'keyid' => 'test-key'],
        ];

        yield 'with integer parameters' => [
            'sig1=("@method" "@target-uri");created=1618884473;expires=1618884773',
            'sig1',
            ['@method', '@target-uri'],
            ['created' => 1618884473, 'expires' => 1618884773],
        ];

        yield 'complex example' => [
            'sig1=("@method" "@target-uri" "content-digest");created=1618884473;alg="ed25519";keyid="test-key-ed25519"',
            'sig1',
            ['@method', '@target-uri', 'content-digest'],
            ['created' => 1618884473, 'alg' => 'ed25519', 'keyid' => 'test-key-ed25519'],
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidSignatureInputHeaders')]
    public function fromStringThrowsExceptionForInvalidHeaders(string $invalid_header): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        HttpMessageSignatureInput::fromString($invalid_header);
    }

    public static function provideInvalidSignatureInputHeaders(): \Generator
    {
        yield 'no components list' => ['sig1'];
        yield 'malformed components list' => ['sig1=(@method)'];
        yield 'missing closing parenthesis' => ['sig1=("@method" "@target-uri"'];
        yield 'unquoted components' => ['sig1=(@method @target-uri)'];
        yield 'malformed parameter' => ['sig1=("@method");invalid_param'];
    }

    #[Test]
    public function parameterAccessorMethods(): void
    {
        $parameters = [
            'created' => 1618884473,
            'expires' => 1618884773,
            'alg' => 'ed25519',
            'keyid' => 'test-key',
            'nonce' => 'random-nonce',
        ];

        $signature_input = new HttpMessageSignatureInput('sig1', ['@method'], $parameters);

        self::assertTrue($signature_input->hasParameter('created'));
        self::assertFalse($signature_input->hasParameter('nonexistent'));

        self::assertSame(1618884473, $signature_input->created());
        self::assertSame(1618884773, $signature_input->expires());
        self::assertSame('ed25519', $signature_input->algorithm());
        self::assertSame('test-key', $signature_input->keyId());
        self::assertSame('random-nonce', $signature_input->nonce());

        self::assertSame(1618884473, $signature_input->getParameter('created'));
        self::assertNull($signature_input->getParameter('nonexistent'));
    }

    #[Test]
    public function parameterAccessorMethodsReturnNullWhenParametersNotPresent(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method']);

        self::assertNull($signature_input->created());
        self::assertNull($signature_input->expires());
        self::assertNull($signature_input->algorithm());
        self::assertNull($signature_input->keyId());
        self::assertNull($signature_input->nonce());
    }

    #[Test]
    public function toStringReconstructsOriginalFormat(): void
    {
        $signature_label = 'sig1';
        $covered_components = ['@method', '@target-uri', 'content-digest'];
        $parameters = ['created' => 1618884473, 'alg' => 'ed25519', 'keyid' => 'test-key-ed25519'];

        $signature_input = new HttpMessageSignatureInput($signature_label, $covered_components, $parameters);
        $string_representation = $signature_input->toString();

        // Parse it back and verify it's equivalent
        $parsed = HttpMessageSignatureInput::fromString($string_representation);

        self::assertSame($signature_input->signatureLabel(), $parsed->signatureLabel());
        self::assertSame($signature_input->coveredComponents(), $parsed->coveredComponents());
        self::assertSame($signature_input->parameters(), $parsed->parameters());
    }

    #[Test]
    public function stringableInterface(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method'], ['alg' => 'ed25519']);

        self::assertSame($signature_input->toString(), (string)$signature_input);
    }

    #[Test]
    public function toStringWithoutParameters(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method', '@target-uri']);
        $expected = 'sig1=("@method" "@target-uri")';

        self::assertSame($expected, $signature_input->toString());
    }

    #[Test]
    public function toStringWithMixedParameterTypes(): void
    {
        $signature_input = new HttpMessageSignatureInput('sig1', ['@method'], [
            'created' => 1618884473,
            'alg' => 'ed25519',
            'expires' => 1618884773,
        ]);

        $result = $signature_input->toString();

        // Verify structure without depending on parameter order
        self::assertStringStartsWith('sig1=("@method")', $result);
        self::assertStringContainsString('created=1618884473', $result);
        self::assertStringContainsString('alg="ed25519"', $result);
        self::assertStringContainsString('expires=1618884773', $result);
    }
}
