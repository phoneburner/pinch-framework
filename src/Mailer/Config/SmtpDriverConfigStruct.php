<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Mailer\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

class SmtpDriverConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        #[\SensitiveParameter] public string $password,
        public bool $encryption = true,
    ) {
    }
}
