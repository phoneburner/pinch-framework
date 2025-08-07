<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransport;

class AmazonSqsTransportFactory implements TransportFactory
{
    public function make(TransportConfigStruct $config): AmazonSqsTransport
    {
        \assert($config->class === AmazonSqsTransport::class);
        throw new \LogicException('Not implemented');
    }
}
