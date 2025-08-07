<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Scheduler;

use Symfony\Component\Scheduler\ScheduleProviderInterface;

interface ScheduleProvider extends ScheduleProviderInterface
{
    public static function getName(): string;
}
