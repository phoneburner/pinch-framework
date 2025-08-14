<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class MessageBusConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param array<string, BusConfigStruct> $bus
     * @param array<class-string, list<class-string>> $handlers
     * @param array<class-string, list<string>> $routing // messages not mapped to a transport are handled synchronously.
     */
    public function __construct(
        public array $bus = [],
        public array $handlers = [],
        public array $routing = [],
        public array $senders = [],
        public array $receivers = [],
        public array $failure_senders = [],
        public array $retry_strategy = [],
        public array $transport_factories = [],
    ) {
    }
}
