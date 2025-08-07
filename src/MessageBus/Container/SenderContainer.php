<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\Container;

use PhoneBurner\Pinch\Container\ObjectContainer\MutableObjectContainer;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * @extends MutableObjectContainer<SenderInterface>
 */
class SenderContainer extends MutableObjectContainer
{
}
