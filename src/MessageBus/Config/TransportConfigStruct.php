<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use Symfony\Component\Messenger\Transport\TransportInterface;

class TransportConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param class-string<TransportInterface> $class
     * @param array<string,mixed> $options
     */
    public function __construct(
        public readonly string $class,
        public readonly string $connection,
        public readonly array $options = [],
    ) {
    }
}
