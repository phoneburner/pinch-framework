<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\HttpExceptionResponseTransformerStrategy;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\TransformerStrategies\JsonResponseTransformerStrategy;
use Psr\Http\Server\MiddlewareInterface;

final readonly class HttpConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param class-string<HttpExceptionResponseTransformerStrategy> $exceptional_response_default_transformer
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        public string $exceptional_response_default_transformer = JsonResponseTransformerStrategy::class,
        public string $logout_redirect_url = '/',
        public RoutingConfigStruct $routing = new RoutingConfigStruct(),
        public SessionConfigStruct $session = new SessionConfigStruct(),
        public RateLimitingConfigStruct|null $global_rate_limiting = null,
        public array $middleware = [],
    ) {
    }
}
