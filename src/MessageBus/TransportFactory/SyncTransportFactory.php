<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\Container\MessageBusContainer;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

use function PhoneBurner\Pinch\Type\narrow;

class SyncTransportFactory implements TransportFactory
{
    public function __construct(private readonly MessageBusContainer $message_bus_locator)
    {
    }

    public function make(TransportConfigStruct $config): TransportInterface
    {
        \assert($config->class === SyncTransport::class);

        return new SyncTransport(narrow(
            MessageBusInterface::class,
            $this->message_bus_locator->get($config->options['bus'] ?? MessageBus::DEFAULT),
        ));
    }
}
