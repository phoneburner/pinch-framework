<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use Symfony\Component\Messenger\MessageBus as SymfonyMessageBus;

#[Internal]
class SymfonyMessageBusAdapter extends SymfonyMessageBus implements MessageBus
{
}
