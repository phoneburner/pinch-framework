<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Scheduler\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Framework\Scheduler\ScheduleProvider;

class SchedulerConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param list<class-string<ScheduleProvider>> $schedule_providers
     */
    public function __construct(
        public array $schedule_providers = [],
    ) {
    }
}
