<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Component\Http\Routing\RequestHandler\NotFoundRequestHandler;
use PhoneBurner\Pinch\Component\Http\Routing\RouteProvider;
use Psr\Http\Server\RequestHandlerInterface;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

final readonly class RoutingConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public const string DEFAULT_CACHE_PATH = APP_ROOT . '/storage/bootstrap/routes.cache.php';

    /**
     * @param class-string<RequestHandlerInterface> $fallback_handler
     * @param list<class-string<RouteProvider>> $route_providers
     */
    public function __construct(
        public bool $enable_cache = false,
        public string|null $cache_path = null,
        public array $route_providers = [],
        public string $fallback_handler = NotFoundRequestHandler::class,
    ) {
    }
}
