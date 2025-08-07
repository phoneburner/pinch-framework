<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Notifier\Slack\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class SlackWebhookNotifierConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public string $endpoint = '',
        public array $options = [],
    ) {
    }
}
