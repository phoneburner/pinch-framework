<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\FileNotFoundResponse;
use PhoneBurner\Pinch\Component\Http\Response\StreamResponse;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Framework\Http\Request\RequestFactory;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\OpenApiRequestHandler;
use PhoneBurner\Pinch\Framework\Tests\TestSupport\MockRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const PhoneBurner\Pinch\UNIT_TEST_ROOT;

final class OpenApiRequestHandlerTest extends TestCase
{
    use MockRequest;

    protected const string JSON_FILE = UNIT_TEST_ROOT . '/Fixtures/test-openapi.json';
    protected const string HTML_FILE = UNIT_TEST_ROOT . '/Fixtures/test-openapi.html';
    protected const string YAML_FILE = UNIT_TEST_ROOT . '/Fixtures/test-openapi.yaml';

    #[\Override]
    protected function setUp(): void
    {
        // Create test files for testing
        if (! \file_exists(self::JSON_FILE)) {
            \file_put_contents(self::JSON_FILE, '{"openapi": "3.0.0", "info": {"title": "Test API", "version": "1.0.0"}}');
        }
        if (! \file_exists(self::HTML_FILE)) {
            \file_put_contents(self::HTML_FILE, '<html><body><h1>OpenAPI Documentation</h1></body></html>');
        }
        if (! \file_exists(self::YAML_FILE)) {
            \file_put_contents(self::YAML_FILE, "openapi: 3.0.0\ninfo:\n  title: Test API\n  version: 1.0.0");
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Clean up test files
        foreach ([self::JSON_FILE, self::HTML_FILE, self::YAML_FILE] as $file) {
            if (\file_exists($file)) {
                @\unlink($file);
            }
        }
    }

    #[DataProvider('provideContentTypeFromRouteAttributes')]
    #[Test]
    public function handleReturnsCorrectContentTypeFromRouteAttributes(
        string $content_type,
        string $expected_file,
    ): void {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([ContentType::class => $content_type]);

        $request = new RequestFactory()->createServerRequest(HttpMethod::Get, '', attributes: [
            RouteMatch::class => $route_match,
        ]);

        $handler = new OpenApiRequestHandler(
            self::JSON_FILE,
            self::HTML_FILE,
            self::YAML_FILE,
        );

        $response = $handler->handle($request);

        self::assertInstanceOf(StreamResponse::class, $response);
        self::assertSame($content_type, $response->getHeaderLine(HttpHeader::CONTENT_TYPE));
        self::assertSame((string)\filesize($expected_file), $response->getHeaderLine(HttpHeader::CONTENT_LENGTH));
        self::assertStringEqualsFile($expected_file, (string)$response->getBody());
    }

    #[DataProvider('provideContentTypeFromAcceptHeader')]
    #[Test]
    public function handleReturnsCorrectContentTypeFromAcceptHeader(
        string $accept_header,
        string $expected_content_type,
        string $expected_file,
    ): void {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([]);

        $request = new RequestFactory()->createServerRequest(
            HttpMethod::Get,
            '',
            headers: [HttpHeader::ACCEPT => $accept_header],
            attributes: [RouteMatch::class => $route_match],
        );

        $handler = new OpenApiRequestHandler(
            self::JSON_FILE,
            self::HTML_FILE,
            self::YAML_FILE,
        );

        $response = $handler->handle($request);

        self::assertInstanceOf(StreamResponse::class, $response);
        self::assertSame($expected_content_type, $response->getHeaderLine(HttpHeader::CONTENT_TYPE));
        self::assertSame((string)\filesize($expected_file), $response->getHeaderLine(HttpHeader::CONTENT_LENGTH));
        self::assertStringEqualsFile($expected_file, (string)$response->getBody());
    }

    #[Test]
    public function handleReturnsHtmlByDefault(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([]);

        $request = new RequestFactory()->createServerRequest(HttpMethod::Get, '', attributes: [
            RouteMatch::class => $route_match,
        ]);

        $handler = new OpenApiRequestHandler(
            self::JSON_FILE,
            self::HTML_FILE,
            self::YAML_FILE,
        );

        $response = $handler->handle($request);

        self::assertInstanceOf(StreamResponse::class, $response);
        self::assertSame(ContentType::HTML, $response->getHeaderLine(HttpHeader::CONTENT_TYPE));
        self::assertSame((string)\filesize(self::HTML_FILE), $response->getHeaderLine(HttpHeader::CONTENT_LENGTH));
        self::assertStringEqualsFile(self::HTML_FILE, (string)$response->getBody());
    }

    #[DataProvider('provideDisabledContentTypes')]
    #[Test]
    public function handleReturnsFileNotFoundWhenContentTypeDisabled(
        string|null $json_path,
        string|null $html_path,
        string|null $yaml_path,
        string $requested_content_type,
    ): void {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([ContentType::class => $requested_content_type]);

        $request = new RequestFactory()->createServerRequest(HttpMethod::Get, '', attributes: [
            RouteMatch::class => $route_match,
        ]);

        $handler = new OpenApiRequestHandler($json_path, $html_path, $yaml_path);
        $response = $handler->handle($request);

        self::assertInstanceOf(FileNotFoundResponse::class, $response);
    }

    #[Test]
    public function handleReturnsFileNotFoundWhenFileDoesNotExist(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([ContentType::class => ContentType::JSON]);

        $request = new RequestFactory()->createServerRequest(HttpMethod::Get, '', attributes: [
            RouteMatch::class => $route_match,
        ]);

        $handler = new OpenApiRequestHandler('/non/existent/file.json', null, null);
        $response = $handler->handle($request);

        self::assertInstanceOf(FileNotFoundResponse::class, $response);
    }

    #[Test]
    public function handleReturnsFileNotFoundWhenRouteMatchMissing(): void
    {
        $request = new RequestFactory()->createServerRequest(HttpMethod::Get, '');

        $handler = new OpenApiRequestHandler(
            self::JSON_FILE,
            self::HTML_FILE,
            self::YAML_FILE,
        );

        $response = $handler->handle($request);

        // Should still work and return HTML by default since RouteMatch is optional
        self::assertInstanceOf(StreamResponse::class, $response);
        self::assertSame(ContentType::HTML, $response->getHeaderLine(HttpHeader::CONTENT_TYPE));
    }

    #[Test]
    public function handlePrioritizesRouteAttributeOverAcceptHeader(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([ContentType::class => ContentType::JSON]);

        $request = new RequestFactory()->createServerRequest(
            HttpMethod::Get,
            '',
            headers: [HttpHeader::ACCEPT => ContentType::YAML],
            attributes: [RouteMatch::class => $route_match],
        );

        $handler = new OpenApiRequestHandler(
            self::JSON_FILE,
            self::HTML_FILE,
            self::YAML_FILE,
        );

        $response = $handler->handle($request);

        self::assertInstanceOf(StreamResponse::class, $response);
        self::assertSame(ContentType::JSON, $response->getHeaderLine(HttpHeader::CONTENT_TYPE));
        self::assertStringEqualsFile(self::JSON_FILE, (string)$response->getBody());
    }

    #[Test]
    public function handleReturnsFileNotFoundForUnsupportedContentType(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([ContentType::class => ContentType::XML]);

        $request = new RequestFactory()->createServerRequest(HttpMethod::Get, '', attributes: [
            RouteMatch::class => $route_match,
        ]);

        $handler = new OpenApiRequestHandler(
            self::JSON_FILE,
            self::HTML_FILE,
            self::YAML_FILE,
        );

        $response = $handler->handle($request);

        self::assertInstanceOf(FileNotFoundResponse::class, $response);
    }

    public static function provideContentTypeFromRouteAttributes(): \Generator
    {
        yield 'JSON from route attributes' => [
            ContentType::JSON,
            self::JSON_FILE,
        ];

        yield 'HTML from route attributes' => [
            ContentType::HTML,
            self::HTML_FILE,
        ];

        yield 'YAML from route attributes' => [
            ContentType::YAML,
            self::YAML_FILE,
        ];
    }

    public static function provideContentTypeFromAcceptHeader(): \Generator
    {
        yield 'JSON from Accept header' => [
            ContentType::JSON,
            ContentType::JSON,
            self::JSON_FILE,
        ];

        yield 'YAML from Accept header' => [
            ContentType::YAML,
            ContentType::YAML,
            self::YAML_FILE,
        ];

        yield 'text/html from Accept header' => [
            'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            ContentType::HTML,
            self::HTML_FILE,
        ];

        yield 'unknown Accept header defaults to HTML' => [
            'application/xml',
            ContentType::HTML,
            self::HTML_FILE,
        ];
    }

    public static function provideDisabledContentTypes(): \Generator
    {
        yield 'JSON disabled' => [
            null,
            self::HTML_FILE,
            self::YAML_FILE,
            ContentType::JSON,
        ];

        yield 'HTML disabled' => [
            self::JSON_FILE,
            null,
            self::YAML_FILE,
            ContentType::HTML,
        ];

        yield 'YAML disabled' => [
            self::JSON_FILE,
            self::HTML_FILE,
            null,
            ContentType::YAML,
        ];

        yield 'All disabled' => [
            null,
            null,
            null,
            ContentType::JSON,
        ];
    }
}
