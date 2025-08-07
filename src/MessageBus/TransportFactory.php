<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus;

use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use Symfony\Component\Messenger\Transport\TransportInterface;

interface TransportFactory
{
    public function make(TransportConfigStruct $config): TransportInterface;
}
