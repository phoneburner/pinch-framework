<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus;

class Transport
{
    final public const string ASYNC = 'async';
    final public const string ASYNC_P0 = 'async-priority-0';
    final public const string ASYNC_P1 = 'async-priority-1';
    final public const string ASYNC_P2 = 'async-priority-2';
    final public const string SYNC = 'sync';
    final public const string FAILED = 'failed';
}
