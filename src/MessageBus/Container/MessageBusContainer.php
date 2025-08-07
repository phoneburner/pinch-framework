<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\Container;

use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use PhoneBurner\Pinch\Container\ObjectContainer\MutableObjectContainer;
use PhoneBurner\Pinch\Framework\MessageBus\SymfonyMessageBusAdapter;

/**
 * @extends MutableObjectContainer<SymfonyMessageBusAdapter>
 */
class MessageBusContainer extends MutableObjectContainer
{
    public function default(): SymfonyMessageBusAdapter
    {
        return $this->entries[MessageBus::DEFAULT] ?? throw new \RuntimeException(
            \sprintf('No default message bus ("%s") found', MessageBus::DEFAULT),
        );
    }
}
