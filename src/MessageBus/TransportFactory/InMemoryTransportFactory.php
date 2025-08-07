<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class InMemoryTransportFactory implements TransportFactory
{
    public function __construct(private readonly Clock $clock)
    {
    }

    public function make(TransportConfigStruct $config): TransportInterface
    {
        \assert($config->class === InMemoryTransport::class);
        return new InMemoryTransport(null, $this->clock);
    }
}
