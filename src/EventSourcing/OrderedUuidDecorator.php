<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing;

use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use PhoneBurner\Pinch\Uuid\Uuid;

class OrderedUuidDecorator implements MessageDecorator
{
    #[\Override]
    public function decorate(Message $message): Message
    {
        return $message->withHeader(Header::EVENT_ID, Uuid::ordered()->toString());
    }
}
