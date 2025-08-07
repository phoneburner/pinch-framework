<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework;

use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceProvider;
use PhoneBurner\Pinch\Framework\ApplicationRouteProvider;

/**
 * @codeCoverageIgnore
 */
class ApplicationServiceProvider implements ServiceProvider
{
    public static function bind(): array
    {
        return [];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(
            ApplicationRouteProvider::class,
            static fn(App $app): ApplicationRouteProvider => new ApplicationRouteProvider(),
        );
    }
}
