<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\Handler;

use Monolog\Handler\LogglyHandler;
use PhoneBurner\Pinch\Container\ResettableService;
use PhoneBurner\Pinch\Time\Timer\StopWatch;

use const PhoneBurner\Pinch\Time\NANOSECONDS_IN_SECOND;

class ResettableLogglyHandler extends LogglyHandler implements ResettableService
{
    private StopWatch|null $timer = null;

    /**
     * If it's been more than 120 seconds since the first time we made a request,
     * reset the curl handlers, which are created/cached in the parent send() call,
     * avoiding stale connection errors.
     */
    #[\Override]
    protected function send(string $data, string $endpoint): void
    {
        $this->timer ??= StopWatch::start();
        if ($this->timer->elapsed()->nanoseconds > 120 * NANOSECONDS_IN_SECOND) {
            $this->reset();
        }

        parent::send($data, $endpoint);
    }

    #[\Override]
    public function reset(): void
    {
        $this->curlHandlers = [];
        $this->timer = null;
    }
}
