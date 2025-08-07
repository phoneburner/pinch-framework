<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck;

use PhoneBurner\Pinch\Framework\HealthCheck\Domain\ComponentHealthCheck;
use PhoneBurner\Pinch\Time\Clock\Clock;

interface ComponentHealthCheckService
{
    /**
     * @return array<ComponentHealthCheck>
     */
    public function __invoke(Clock $clock): array;
}
