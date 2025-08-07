<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;

class AmqpTransportFactory implements TransportFactory
{
    public function make(TransportConfigStruct $config): AmqpTransport
    {
        \assert($config->class === AmqpTransport::class);
        throw new \LogicException('Not implemented');
    }
}
