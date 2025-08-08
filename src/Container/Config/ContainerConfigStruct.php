<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Container\Config;

use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

final readonly class ContainerConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param list<class-string<ServiceProvider>> $service_providers
     */
    public function __construct(
        public bool $enable_deferred_service_registration = false,
        public array $service_providers = [],
    ) {
    }
}
