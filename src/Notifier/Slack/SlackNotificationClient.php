<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Notifier\Slack;

use PhoneBurner\Pinch\Time\TimeInterval\TimeInterval;

interface SlackNotificationClient
{
    public function send(SlackNotification $notification, TimeInterval|null $ttl = null): bool;
}
