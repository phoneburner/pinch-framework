<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Event;

use PhoneBurner\Pinch\Attribute\Psr14Event;
use PhoneBurner\Pinch\Component\Http\RequestAware;
use PhoneBurner\Pinch\Component\Logging\LogEntry;
use PhoneBurner\Pinch\Component\Logging\Loggable;
use PhoneBurner\Pinch\Component\Logging\LogLevel;
use Psr\Http\Message\RequestInterface;

#[Psr14Event]
final readonly class NotImplementedRequestHandled implements RequestAware, Loggable
{
    public function __construct(public RequestInterface $request)
    {
    }

    public function getLogEntry(): LogEntry
    {
        return new LogEntry(LogLevel::Debug, 'Not Implemented Request Handled');
    }
}
