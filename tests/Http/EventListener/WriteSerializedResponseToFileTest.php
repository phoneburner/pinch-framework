<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\EventListener;

use PhoneBurner\Pinch\Component\Http\Message\ResponseSerializer;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Http\EventListener\WriteSerializedResponseToFile;
use PhoneBurner\Pinch\Framework\Tests\Http\EventListener\Fixtures\MockResponseAwareEvent;
use PhoneBurner\Pinch\Uuid\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class WriteSerializedResponseToFileTest extends TestCase
{
    private const string TEST_DIRECTORY = __DIR__ . '/tmp-responses/';

    private ResponseSerializer&MockObject $serializer;

    private LogTrace $log_trace;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        \mkdir(self::TEST_DIRECTORY, 0777, true);
        if (! \is_dir(self::TEST_DIRECTORY)) {
            throw new \RuntimeException('Failed to create test directory');
        }

        $this->serializer = $this->createMock(ResponseSerializer::class);
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
    public function invokeWritesSerializedResponseToFile(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $event = new MockResponseAwareEvent($response);
        $serialized_content = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n{\"status\":\"success\"}";

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($response)
            ->willReturn($serialized_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedResponseToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_response.http';
        self::assertFileExists($expected_filename);

        $written_content = \file_get_contents($expected_filename);
        self::assertSame($serialized_content, $written_content);
    }

    #[Test]
    #[DataProvider('provideResponseTypes')]
    public function invokeHandlesDifferentResponseTypes(string $expected_content): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $event = new MockResponseAwareEvent($response);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($response)
            ->willReturn($expected_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedResponseToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_response.http';
        self::assertFileExists($expected_filename);

        $written_content = \file_get_contents($expected_filename);
        self::assertSame($expected_content, $written_content);
    }

    #[Test]
    public function invokeHandlesLargeResponseContent(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $event = new MockResponseAwareEvent($response);

        // Create a large serialized content string (1MB+)
        $large_content = \str_repeat('A', 1024 * 1024 + 1);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($response)
            ->willReturn($large_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedResponseToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_response.http';
        self::assertFileExists($expected_filename);

        $written_content = \file_get_contents($expected_filename);
        self::assertSame($large_content, $written_content);
    }

    #[Test]
    public function invokeLogsErrorWhenSerializerThrowsException(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $event = new MockResponseAwareEvent($response);
        $exception = new \RuntimeException('Serialization failed');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($response)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to write serialized response to file',
                ['exception' => $exception],
            );

        $listener = new WriteSerializedResponseToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        // Verify no file was created
        $expected_filename = self::TEST_DIRECTORY . '/' . $this->log_trace . '_response.http';
        self::assertFileDoesNotExist($expected_filename);
    }

    #[Test]
    public function invokeLogsErrorWhenFileWritingFails(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $event = new MockResponseAwareEvent($response);

        $exception = new \RuntimeException('Failed to create directory');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($response)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to write serialized response to file',
                self::callback(static fn(array $context): bool => isset($context['exception']) && $context['exception'] === $exception),
            );

        $listener = new WriteSerializedResponseToFile(
            $this->serializer,
            $this->log_trace,
            $this->logger,
        );

        $listener($event);
    }

    #[Test]
    public function invokeUsesCorrectFilenameFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $event = new MockResponseAwareEvent($response);
        $serialized_content = "HTTP/1.1 404 Not Found\r\nContent-Type: application/problem+json\r\n\r\n{\"type\":\"not-found\"}";

        $custom_log_trace = new LogTrace(Uuid::instance('fedcba98-7654-3210-fedc-ba9876543210'));

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($response)
            ->willReturn($serialized_content);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $listener = new WriteSerializedResponseToFile(
            $this->serializer,
            $custom_log_trace,
            $this->logger,
            self::TEST_DIRECTORY . '/',
        );

        $listener($event);

        $expected_filename = self::TEST_DIRECTORY . '/' . $custom_log_trace->toString() . '_response.http';
        self::assertFileExists($expected_filename);
    }

    public static function provideResponseTypes(): \Generator
    {
        yield 'Success response' => [
            "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nCache-Control: no-cache\r\n\r\n{\"success\":true}",
        ];

        yield 'Created response' => [
            "HTTP/1.1 201 Created\r\nContent-Type: application/json\r\nLocation: /api/users/123\r\n\r\n{\"id\":123,\"name\":\"John\"}",
        ];

        yield 'Bad request response' => [
            "HTTP/1.1 400 Bad Request\r\nContent-Type: application/problem+json\r\n\r\n{\"type\":\"validation-error\",\"title\":\"Validation Failed\"}",
        ];

        yield 'Not found response' => [
            "HTTP/1.1 404 Not Found\r\nContent-Type: application/problem+json\r\n\r\n{\"type\":\"not-found\",\"title\":\"Resource Not Found\"}",
        ];

        yield 'Server error response' => [
            "HTTP/1.1 500 Internal Server Error\r\nContent-Type: application/problem+json\r\n\r\n{\"type\":\"server-error\",\"title\":\"Internal Server Error\"}",
        ];
    }
}
