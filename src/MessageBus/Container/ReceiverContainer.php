<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\Container;

use PhoneBurner\Pinch\Container\ObjectContainer\MutableObjectContainer;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @extends MutableObjectContainer<ReceiverInterface>
 */
class ReceiverContainer extends MutableObjectContainer
{
}
