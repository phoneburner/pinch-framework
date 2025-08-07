<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Mailer\Transport;

use PhoneBurner\Pinch\Enum\Trait\WithStringBackedInstanceStaticMethod;

enum TransportDriver: string
{
    use WithStringBackedInstanceStaticMethod;

    case Smtp = 'smtp';
    case SendGrid = 'sendgrid';
    case None = 'none';
}
