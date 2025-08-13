<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\EventListener;

use PhoneBurner\Pinch\Component\Http\Message\RequestSerializer;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Http\EventListener\WriteSerializedRequestToFile;
use PhoneBurner\Pinch\Framework\Tests\Http\EventListener\Fixtures\MockRequestAwareEvent;
use PhoneBurner\Pinch\Uuid\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

final class WriteSerializedRequestToFileTest extends TestCase
{
    private const string TEST_DIRECTORY = __DIR__ . '/tmp-request/';

    private RequestSerializer&MockObject $serializer;

    private LogTrace $log_trace;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        \mkdir(self::TEST_DIRECTORY, 0777, true);
        if (! \is_dir(self::TEST_DIRECTORY)) {
            throw new \RuntimeException('Failed to create test directory');
        }

        $this->serializer = $this->createMock(RequestSerializer::class);
        $this->log_trace = new LogTrace(Uuid::instance('10554035-5bcb-4c0a-8f74-fcd745268359'));
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directory and all files
        if (\is_dir(self::TEST_DIRECTORY)) {
            foreach (\glob(self::TEST_DIRECTORY . '/*') ?: [] as $file) {
                @\unlink($file);
            }
            @\rmdir(self::TEST_DIRECTORY);
        }
    }

    #[Test]
    public function invokeWritesSerializedRequestToFile(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new MockRequestAwareEvent($request);
        $serialized_content = "GET /test HTTP/1.1\r\nHost: example.com\r\n\r\n";

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($request)
            ->willReturn($serialized_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedRequestToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_request.http';
        self::assertFileExists($expected_filename);

        $written_content = \file_get_contents($expected_filename);
        self::assertSame($serialized_content, $written_content);
    }

    #[Test]
    #[DataProvider('provideRequestTypes')]
    public function invokeHandlesDifferentRequestTypes(string $expected_content): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new MockRequestAwareEvent($request);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($request)
            ->willReturn($expected_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedRequestToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_request.http';
        self::assertFileExists($expected_filename);

        $written_content = \file_get_contents($expected_filename);
        self::assertSame($expected_content, $written_content);
    }

    #[Test]
    public function invokeHandlesLargeRequestContent(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new MockRequestAwareEvent($request);

        // Create a large serialized content string (1MB+)
        $large_content = \str_repeat('A', 1024 * 1024 + 1);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($request)
            ->willReturn($large_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedRequestToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_request.http';
        self::assertFileExists($expected_filename);

        $written_content = \file_get_contents($expected_filename);
        self::assertSame($large_content, $written_content);
    }

    #[Test]
    public function invokeLogsErrorWhenSerializerThrowsException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new MockRequestAwareEvent($request);
        $exception = new \RuntimeException('Serialization failed');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($request)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to write serialized request to file',
                ['exception' => $exception],
            );

        $listener = new WriteSerializedRequestToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        // Verify no file was created
        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_request.http';
        self::assertFileDoesNotExist($expected_filename);
    }

    #[Test]
    public function invokeLogsErrorWhenFileWritingFails(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new MockRequestAwareEvent($request);

        $exception = new \RuntimeException('Failed to create directory');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($request)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to write serialized request to file',
                self::callback(static fn(array $context): bool => isset($context['exception']) && $context['exception'] === $exception),
            );

        // Use an invalid file path that should cause FileWriter to throw
        $invalid_path = '/invalid/path/that/cannot/be/created/';

        $listener = new WriteSerializedRequestToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            $invalid_path,
        );

        $listener($event);
    }

    #[Test]
    public function invokeUsesCorrectFilenameFormat(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new MockRequestAwareEvent($request);
        $serialized_content = "GET /test HTTP/1.1\r\nHost: example.com\r\n\r\n";

        $custom_log_trace = new LogTrace(Uuid::instance('fedcba98-7654-3210-fedc-ba9876543210'));

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($request)
            ->willReturn($serialized_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedRequestToFile(
            $this->serializer,
            $custom_log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $custom_log_trace->toString() . '_request.http';
        self::assertFileExists($expected_filename);
    }

    public static function provideRequestTypes(): \Generator
    {
        yield 'GET request' => [
            "GET /api/users HTTP/1.1\r\nHost: api.example.com\r\nUser-Agent: TestClient/1.0\r\n\r\n",
        ];

        yield 'POST request with body' => [
            "POST /api/users HTTP/1.1\r\nHost: api.example.com\r\nContent-Type: application/json\r\nContent-Length: 25\r\n\r\n{\"name\":\"John\",\"age\":30}",
        ];

        yield 'PUT request with headers' => [
            "PUT /api/users/123 HTTP/1.1\r\nHost: api.example.com\r\nAuthorization: Bearer token123\r\nContent-Type: application/json\r\n\r\n{\"name\":\"Jane\"}",
        ];

        yield 'DELETE request' => [
            "DELETE /api/users/123 HTTP/1.1\r\nHost: api.example.com\r\nAuthorization: Bearer token123\r\n\r\n",
        ];

        yield 'Complex request with multiple headers' => [
            "PATCH /api/users/123 HTTP/1.1\r\nHost: api.example.com\r\nAuthorization: Bearer token123\r\nContent-Type: application/json\r\nAccept: application/hal+json\r\nX-Custom-Header: custom-value\r\n\r\n{\"status\":\"active\"}",
        ];
    }
}
