<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Event;

use PhoneBurner\Pinch\Component\Logging\LogLevel;
use PhoneBurner\Pinch\Framework\Http\Event\NotImplementedRequestHandled;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

final class NotImplementedRequestHandledTest extends TestCase
{
    #[Test]
    public function constructorAcceptsValidRequestInterface(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $event = new NotImplementedRequestHandled($request);

        self::assertSame($request, $event->request);
    }

    #[Test]
    public function getLogEntryReturnsCorrectLogEntryWithDebugLevel(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new NotImplementedRequestHandled($request);

        $log_entry = $event->getLogEntry();

        self::assertSame(LogLevel::Debug, $log_entry->level);
        self::assertSame('Not Implemented Request Handled', $log_entry->message);
    }

    #[Test]
    public function getLogEntryReturnsConsistentLogEntry(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new NotImplementedRequestHandled($request);

        $log_entry_1 = $event->getLogEntry();
        $log_entry_2 = $event->getLogEntry();

        self::assertEquals($log_entry_1->level, $log_entry_2->level);
        self::assertEquals($log_entry_1->message, $log_entry_2->message);
    }

    #[Test]
    public function constructorWorksWithMultipleDifferentRequests(): void
    {
        $request_1 = $this->createMock(RequestInterface::class);
        $request_2 = $this->createMock(RequestInterface::class);

        $request_1->method('getMethod')->willReturn('GET');
        $request_2->method('getMethod')->willReturn('POST');

        $event_1 = new NotImplementedRequestHandled($request_1);
        $event_2 = new NotImplementedRequestHandled($request_2);

        self::assertSame($request_1, $event_1->request);
        self::assertSame($request_2, $event_2->request);
    }

    #[Test]
    public function eventIsImmutableAfterConstruction(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new NotImplementedRequestHandled($request);

        self::assertSame($request, $event->request);

        $log_entry_1 = $event->getLogEntry();
        $log_entry_2 = $event->getLogEntry();

        self::assertEquals($log_entry_1, $log_entry_2);
        self::assertNotSame($log_entry_1, $log_entry_2); // Different instances
    }

    #[Test]
    public function requestIsAccessibleThroughEvent(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn($this->createMock(UriInterface::class));

        $event = new NotImplementedRequestHandled($request);

        self::assertSame($request, $event->request);

        self::assertSame('POST', $event->request->getMethod());
        self::assertInstanceOf(UriInterface::class, $event->request->getUri());
    }

    #[Test]
    public function logEntryContainsConsistentContext(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $event = new NotImplementedRequestHandled($request);

        $log_entry = $event->getLogEntry();

        self::assertSame(LogLevel::Debug, $log_entry->level);
        self::assertSame('Not Implemented Request Handled', $log_entry->message);

        $second_log_entry = $event->getLogEntry();
        self::assertEquals($log_entry->level, $second_log_entry->level);
        self::assertSame($log_entry->message, $second_log_entry->message);
        self::assertNotSame($log_entry, $second_log_entry);
    }

    #[Test]
    public function eventSupportsLoggingWorkflow(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        $event = new NotImplementedRequestHandled($request);
        $log_entry = $event->getLogEntry();

        self::assertSame(LogLevel::Debug, $log_entry->level);
        self::assertSame('Not Implemented Request Handled', $log_entry->message);

        self::assertSame($request, $event->request);
        self::assertSame('GET', $event->request->getMethod());

        $another_log_entry = $event->getLogEntry();
        self::assertEquals($log_entry->level, $another_log_entry->level);
        self::assertSame($log_entry->message, $another_log_entry->message);
    }
}
