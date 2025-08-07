<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Mailer\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Component\EmailAddress\EmailAddress;
use PhoneBurner\Pinch\Framework\Mailer\Transport\TransportDriver;

final readonly class MailerConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param array<value-of<TransportDriver>|string, ConfigStruct> $drivers
     */
    public function __construct(
        public EmailAddress $default_from_address = new EmailAddress('donotreply@example.com'),
        public TransportDriver|string $default_driver = TransportDriver::None,
        public bool $async = false,
        public array $drivers = [],
    ) {
    }
}
