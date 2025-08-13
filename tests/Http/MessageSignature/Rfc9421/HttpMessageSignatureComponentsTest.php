<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureComponents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpMessageSignatureComponents::class)]
final class HttpMessageSignatureComponentsTest extends TestCase
{
    #[Test]
    public function constructorCreatesValidSignatureComponents(): void
    {
        $components = [
            '@method' => 'GET',
            '@target-uri' => 'https://example.com/test',
            'content-digest' => 'sha-256=abc123',
        ];

        $signature_components = new HttpMessageSignatureComponents($components);

        self::assertSame($components, $signature_components->components());
    }

    #[Test]
    public function constructorCanonicalizesComponentNames(): void
    {
        $components = [
            'Content-Type' => 'application/json',
            'HOST' => 'example.com',
            'accept' => 'application/json',
        ];

        $signature_components = new HttpMessageSignatureComponents($components);
        $result = $signature_components->components();

        self::assertArrayHasKey('content-type', $result);
        self::assertArrayHasKey('host', $result);
        self::assertArrayHasKey('accept', $result);
        self::assertSame('application/json', $result['content-type']);
        self::assertSame('example.com', $result['host']);
        self::assertSame('application/json', $result['accept']);
    }

    #[Test]
    public function constructorThrowsExceptionForEmptyComponents(): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);
        $this->expectExceptionMessage('Components list cannot be empty');

        new HttpMessageSignatureComponents([]);
    }

    #[Test]
    #[DataProvider('provideInvalidComponents')]
    public function constructorThrowsExceptionForInvalidComponents(array $invalid_components): void
    {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        new HttpMessageSignatureComponents($invalid_components);
    }

    public static function provideInvalidComponents(): \Generator
    {
        yield 'empty component name' => [['' => 'value']];
        yield 'non-string component name' => [[123 => 'value']];
        yield 'non-string component value' => [['@method' => 123]];
    }

    #[Test]
    #[DataProvider('provideValidDerivedComponents')]
    public function constructorAcceptsValidDerivedComponents(string $component_name, string $component_value): void
    {
        $components = [$component_name => $component_value];

        $signature_components = new HttpMessageSignatureComponents($components);

        self::assertSame($component_value, $signature_components->getComponent($component_name));
    }

    public static function provideValidDerivedComponents(): \Generator
    {
        yield '@method' => ['@method', 'GET'];
        yield '@target-uri with https' => ['@target-uri', 'https://example.com/path'];
        yield '@target-uri with path only' => ['@target-uri', '/path/to/resource'];
        yield '@status' => ['@status', '200'];
        yield '@authority' => ['@authority', 'example.com'];
        yield '@scheme' => ['@scheme', 'https'];
        yield '@path' => ['@path', '/api/v1/users'];
        yield '@query' => ['@query', 'limit=10&offset=0'];
    }

    #[Test]
    #[DataProvider('provideInvalidDerivedComponents')]
    public function constructorThrowsExceptionForInvalidDerivedComponents(
        string $component_name,
        string $component_value,
    ): void {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        new HttpMessageSignatureComponents([$component_name => $component_value]);
    }

    public static function provideInvalidDerivedComponents(): \Generator
    {
        yield '@method empty' => ['@method', ''];
        yield '@method invalid format' => ['@method', 'g3t'];
        yield '@target-uri empty' => ['@target-uri', ''];
        yield '@target-uri invalid' => ['@target-uri', 'invalid-uri'];
        yield '@status invalid format' => ['@status', '99'];
        yield '@status invalid range' => ['@status', '600'];
        yield '@authority empty' => ['@authority', ''];
        yield '@scheme empty' => ['@scheme', ''];
        yield '@scheme invalid' => ['@scheme', 'ftp'];
        yield '@path invalid' => ['@path', 'no-leading-slash'];
    }

    #[Test]
    public function fromArrayFactoryMethod(): void
    {
        $components = ['@method' => 'POST', 'content-type' => 'application/json'];

        $signature_components = HttpMessageSignatureComponents::fromArray($components);

        self::assertSame($components, $signature_components->components());
    }

    #[Test]
    public function fromHttpMessageFactoryMethod(): void
    {
        $method = 'POST';
        $target_uri = 'https://example.com/api/users';
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123',
        ];
        $status = 201;

        $signature_components = HttpMessageSignatureComponents::fromHttpMessage($method, $target_uri, $headers, $status);
        $result = $signature_components->components();

        self::assertSame('POST', $result['@method']);
        self::assertSame($target_uri, $result['@target-uri']);
        self::assertSame('201', $result['@status']);
        self::assertSame('application/json', $result['content-type']);
        self::assertSame('Bearer token123', $result['authorization']);
    }

    #[Test]
    public function fromHttpMessageWithoutStatus(): void
    {
        $signature_components = HttpMessageSignatureComponents::fromHttpMessage('GET', '/test', ['host' => 'example.com']);
        $result = $signature_components->components();

        self::assertSame('GET', $result['@method']);
        self::assertSame('/test', $result['@target-uri']);
        self::assertArrayNotHasKey('@status', $result);
        self::assertSame('example.com', $result['host']);
    }

    #[Test]
    #[DataProvider('provideInvalidHttpMessageData')]
    public function fromHttpMessageThrowsExceptionForInvalidData(
        string $method,
        string $target_uri,
        array $headers,
    ): void {
        $this->expectException(InvalidHttpMessageSignatureInput::class);

        HttpMessageSignatureComponents::fromHttpMessage($method, $target_uri, $headers);
    }

    public static function provideInvalidHttpMessageData(): \Generator
    {
        yield 'empty header name' => ['GET', '/test', ['' => 'value']];
        yield 'non-string header name' => ['GET', '/test', [123 => 'value']];
        yield 'non-string header value' => ['GET', '/test', ['content-type' => 123]];
    }

    #[Test]
    public function hasComponentMethod(): void
    {
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'GET',
            'Content-Type' => 'application/json',
        ]);

        self::assertTrue($signature_components->hasComponent('@method'));
        self::assertTrue($signature_components->hasComponent('content-type'));
        self::assertTrue($signature_components->hasComponent('Content-Type')); // Case insensitive
        self::assertFalse($signature_components->hasComponent('authorization'));
    }

    #[Test]
    public function getComponentMethod(): void
    {
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'POST',
            'Content-Type' => 'application/json',
        ]);

        self::assertSame('POST', $signature_components->getComponent('@method'));
        self::assertSame('application/json', $signature_components->getComponent('content-type'));
        self::assertSame('application/json', $signature_components->getComponent('Content-Type')); // Case insensitive
        self::assertNull($signature_components->getComponent('authorization'));
    }

    #[Test]
    public function derivedComponentAccessorMethods(): void
    {
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'PUT',
            '@target-uri' => 'https://example.com/api',
            '@status' => '204',
        ]);

        self::assertSame('PUT', $signature_components->method());
        self::assertSame('https://example.com/api', $signature_components->targetUri());
        self::assertSame('204', $signature_components->status());
    }

    #[Test]
    public function derivedComponentAccessorMethodsReturnNullWhenNotPresent(): void
    {
        $signature_components = new HttpMessageSignatureComponents(['content-type' => 'application/json']);

        self::assertNull($signature_components->method());
        self::assertNull($signature_components->targetUri());
        self::assertNull($signature_components->status());
    }

    #[Test]
    public function getHeaderMethod(): void
    {
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'GET',
            'Authorization' => 'Bearer token123',
            'content-type' => 'application/json',
        ]);

        self::assertSame('Bearer token123', $signature_components->getHeader('authorization'));
        self::assertSame('Bearer token123', $signature_components->getHeader('Authorization')); // Case insensitive
        self::assertSame('application/json', $signature_components->getHeader('content-type'));
        self::assertNull($signature_components->getHeader('nonexistent'));
    }

    #[Test]
    public function withComponentReturnsNewInstance(): void
    {
        $original = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $modified = $original->withComponent('content-type', 'application/json');

        self::assertNotSame($original, $modified);
        self::assertFalse($original->hasComponent('content-type'));
        self::assertTrue($modified->hasComponent('content-type'));
        self::assertSame('application/json', $modified->getComponent('content-type'));
    }

    #[Test]
    public function withComponentValidatesDerivedComponents(): void
    {
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $this->expectException(InvalidHttpMessageSignatureInput::class);

        $signature_components->withComponent('@status', 'invalid-status');
    }

    #[Test]
    public function withoutComponentReturnsNewInstance(): void
    {
        $original = new HttpMessageSignatureComponents([
            '@method' => 'GET',
            'content-type' => 'application/json',
        ]);

        $modified = $original->withoutComponent('content-type');

        self::assertNotSame($original, $modified);
        self::assertTrue($original->hasComponent('content-type'));
        self::assertFalse($modified->hasComponent('content-type'));
        self::assertTrue($modified->hasComponent('@method'));
    }

    #[Test]
    public function withoutComponentThrowsExceptionWhenResultWouldBeEmpty(): void
    {
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        $this->expectException(InvalidHttpMessageSignatureInput::class);
        $this->expectExceptionMessage('Cannot remove component: components list would be empty');

        $signature_components->withoutComponent('@method');
    }

    #[Test]
    public function getComponentNamesReturnsAllComponentNames(): void
    {
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'POST',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token',
        ]);

        $component_names = $signature_components->getComponentNames();

        self::assertContains('@method', $component_names);
        self::assertContains('content-type', $component_names);
        self::assertContains('authorization', $component_names);
        self::assertCount(3, $component_names);
    }

    #[Test]
    public function toStringReturnsFormattedComponentList(): void
    {
        $signature_components = new HttpMessageSignatureComponents([
            '@method' => 'GET',
            'host' => 'example.com',
            'content-type' => 'application/json',
        ]);

        $result = $signature_components->toString();

        self::assertStringContainsString('@method: GET', $result);
        self::assertStringContainsString('host: example.com', $result);
        self::assertStringContainsString('content-type: application/json', $result);
    }

    #[Test]
    public function stringableInterface(): void
    {
        $signature_components = new HttpMessageSignatureComponents(['@method' => 'GET']);

        self::assertSame($signature_components->toString(), (string)$signature_components);
    }
}
