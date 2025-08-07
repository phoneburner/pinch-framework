<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Notifier\Email;

use PhoneBurner\Pinch\Attribute\Usage\Contract;
use PhoneBurner\Pinch\Component\Mailer\Mailable;

/**
 * Used for "simple" email notifications that don't require additional headers
 * or attachments, and will be sent with the default global "from" address.
 */
#[Contract]
interface MailableNotification extends Mailable
{
}
