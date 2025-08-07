<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Scheduler\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;

#[AsCommand(
    name: 'messenger:schedule',
    description: 'Generates and dispatch scheduled messages on the message bus',
)]
class ConsumeScheduledMessagesCommand extends ConsumeMessagesCommand
{
}
