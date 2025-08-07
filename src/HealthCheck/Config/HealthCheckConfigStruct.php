<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthCheckService;

final readonly class HealthCheckConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param list<class-string<ComponentHealthCheckService>> $services
     */
    public function __construct(public array $services = [])
    {
    }
}
