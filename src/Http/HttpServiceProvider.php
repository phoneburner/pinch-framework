<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\App\ServiceFactory\NewInstanceServiceFactory;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Http\Cookie\CookieJar;
use PhoneBurner\Pinch\Component\Http\Middleware\LazyMiddlewareRequestHandlerFactory;
use PhoneBurner\Pinch\Component\Http\Middleware\MiddlewareRequestHandlerFactory;
use PhoneBurner\Pinch\Component\Http\Middleware\ThrottleRequests;
use PhoneBurner\Pinch\Component\Http\RateLimiter\NullRateLimiter;
use PhoneBurner\Pinch\Component\Http\RateLimiter\RateLimiter;
use PhoneBurner\Pinch\Component\Http\RequestFactory;
use PhoneBurner\Pinch\Component\Http\RequestHandlerFactory;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\TransformerStrategies\HtmlResponseTransformerStrategy;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\TransformerStrategies\JsonResponseTransformerStrategy;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\TransformerStrategies\TextResponseTransformerStrategy;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\LazyConfigDefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\RequestHandler\NotFoundRequestHandler;
use PhoneBurner\Pinch\Component\Http\Routing\RequestHandler\StaticFileRequestHandler;
use PhoneBurner\Pinch\Component\Http\Routing\RouteProvider;
use PhoneBurner\Pinch\Component\Http\Routing\Router;
use PhoneBurner\Pinch\Component\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Component\Http\Session\SessionManager as SessionManagerContract;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\Http\Config\HttpConfigStruct;
use PhoneBurner\Pinch\Framework\Http\Cookie\CookieEncrypter;
use PhoneBurner\Pinch\Framework\Http\Cookie\Middleware\ManageCookies;
use PhoneBurner\Pinch\Framework\Http\Emitter\MappingEmitter;
use PhoneBurner\Pinch\Framework\Http\Middleware\CatchExceptionalResponses;
use PhoneBurner\Pinch\Framework\Http\Middleware\TransformHttpExceptionResponses;
use PhoneBurner\Pinch\Framework\Http\RateLimiter\RedisRateLimiter;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\CspViolationReportRequestHandler;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\ErrorRequestHandler;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\LogoutRequestHandler;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CacheRoutesCommand;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\ListRoutesCommand;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRoute\FastRouteDispatcherFactory;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRoute\FastRouteResultFactory;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRouter;
use PhoneBurner\Pinch\Framework\Http\Routing\Middleware\AttachRouteToRequest;
use PhoneBurner\Pinch\Framework\Http\Routing\Middleware\DispatchRouteMiddleware;
use PhoneBurner\Pinch\Framework\Http\Routing\Middleware\DispatchRouteRequestHandler;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandlerServiceFactory;
use PhoneBurner\Pinch\Framework\Http\Session\SessionManager;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Redis;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\Type\narrow;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class HttpServiceProvider implements DeferrableServiceProvider
{
    public static function provides(): array
    {
        return [
            Router::class,
            HttpKernel::class,
            RequestFactory::class,
            RequestHandlerFactory::class,
            EmitterInterface::class,
            MiddlewareRequestHandlerFactory::class,
            RequestHandlerInterface::class,
            TransformHttpExceptionResponses::class,
            CatchExceptionalResponses::class,
            NotFoundRequestHandler::class,
            CspViolationReportRequestHandler::class,
            ErrorRequestHandler::class,
            FastRouter::class,
            FastRouteDispatcherFactory::class,
            FastRouteResultFactory::class,
            DefinitionList::class,
            ListRoutesCommand::class,
            CacheRoutesCommand::class,
            CookieEncrypter::class,
            CookieJar::class,
            ManageCookies::class,
            AttachRouteToRequest::class,
            DispatchRouteMiddleware::class,
            DispatchRouteRequestHandler::class,
            StaticFileRequestHandler::class,
            JsonResponseTransformerStrategy::class,
            HtmlResponseTransformerStrategy::class,
            TextResponseTransformerStrategy::class,
            SessionHandler::class,
            SessionManager::class,
            SessionManagerContract::class,
            LogoutRequestHandler::class,
            RateLimiter::class,
            ThrottleRequests::class,
        ];
    }

    public static function bind(): array
    {
        return [
            Router::class => FastRouter::class,
            SessionManagerContract::class => SessionManager::class,
            RateLimiter::class => RedisRateLimiter::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(
            HttpKernel::class,
            static fn(App $app): HttpKernel => new HttpKernel(
                $app->get(RequestFactory::class),
                $app->get(RequestHandlerInterface::class),
                $app->get(EmitterInterface::class),
                $app->environment->stage,
                $app->get(EventDispatcherInterface::class),
            ),
        );

        $app->set(
            RequestFactory::class,
            static fn(App $app): RequestFactory => new RequestFactory(),
        );

        $app->set(
            RequestHandlerFactory::class,
            static fn(App $app): RequestHandlerFactory => new RequestHandlerFactory($app),
        );

        $app->set(
            EmitterInterface::class,
            static fn(App $app): EmitterInterface => new MappingEmitter(),
        );

        $app->set(
            MiddlewareRequestHandlerFactory::class,
            static fn(App $app): MiddlewareRequestHandlerFactory => new LazyMiddlewareRequestHandlerFactory(
                $app->services,
                $app->get(EventDispatcherInterface::class),
            ),
        );

        $app->set(
            RequestHandlerInterface::class,
            static fn(App $app): RequestHandlerInterface => $app->get(MiddlewareRequestHandlerFactory::class)->queue(
                narrow(RequestHandlerInterface::class, $app->get(
                    $app->config->get('http.routing.fallback_request_handler') ?? NotFoundRequestHandler::class,
                )),
                $app->config->get('http.middleware') ?? [],
            ),
        );

        $app->set(
            TransformHttpExceptionResponses::class,
            static fn(App $app): TransformHttpExceptionResponses => new TransformHttpExceptionResponses(
                $app->get(LogTrace::class),
                $app->config->get('http.exceptional_responses.default_transformer') ?: TextResponseTransformerStrategy::class,
            ),
        );

        $app->set(JsonResponseTransformerStrategy::class, NewInstanceServiceFactory::singleton());

        $app->set(HtmlResponseTransformerStrategy::class, NewInstanceServiceFactory::singleton());

        $app->set(TextResponseTransformerStrategy::class, NewInstanceServiceFactory::singleton());

        $app->set(
            CatchExceptionalResponses::class,
            static fn(App $app): CatchExceptionalResponses => new CatchExceptionalResponses(
                $app->get(LoggerInterface::class),
                $app->environment->stage,
                $app->environment->context,
            ),
        );

        $app->set(
            NotFoundRequestHandler::class,
            static fn(App $app): NotFoundRequestHandler => new NotFoundRequestHandler(
                $app->get(LoggerInterface::class),
            ),
        );

        $app->set(
            CspViolationReportRequestHandler::class,
            static fn(App $app): CspViolationReportRequestHandler => new CspViolationReportRequestHandler(
                $app->get(LoggerInterface::class),
            ),
        );

        $app->set(ErrorRequestHandler::class, NewInstanceServiceFactory::singleton());

        $app->set(
            FastRouter::class,
            static fn(App $app): FastRouter => new FastRouter(
                $app->get(DefinitionList::class),
                $app->get(FastRouteDispatcherFactory::class),
                $app->get(FastRouteResultFactory::class),
            ),
        );

        $app->set(
            FastRouteDispatcherFactory::class,
            static fn(App $app): FastRouteDispatcherFactory => new FastRouteDispatcherFactory(
                $app->get(LoggerInterface::class),
                $app->config->get('http.routing'),
            ),
        );

        $app->set(FastRouteResultFactory::class, NewInstanceServiceFactory::singleton());

        $app->set(
            DefinitionList::class,
            static fn(App $app): DefinitionList => LazyConfigDefinitionList::makeFromCallable(...\array_map(
                static fn(string $provider): RouteProvider => narrow(RouteProvider::class, new $provider()),
                $app->config->get('http.routing.route_providers') ?? [],
            )),
        );

        $app->set(
            ListRoutesCommand::class,
            static fn(App $app): ListRoutesCommand => new ListRoutesCommand($app->get(DefinitionList::class)),
        );

        $app->set(
            CacheRoutesCommand::class,
            static fn(App $app): CacheRoutesCommand => new CacheRoutesCommand(
                $app->config,
                $app->get(FastRouter::class),
            ),
        );

        $app->set(
            CookieEncrypter::class,
            ghost(static fn(CookieEncrypter $ghost): null => $ghost->__construct(
                $app->get(Natrium::class),
            )),
        );

        $app->set(CookieJar::class, NewInstanceServiceFactory::singleton());

        $app->set(
            ManageCookies::class,
            static fn(App $app): ManageCookies => new ManageCookies(
                $app->get(CookieJar::class),
                $app->get(CookieEncrypter::class),
                $app->get(Clock::class),
            ),
        );

        $app->set(
            AttachRouteToRequest::class,
            static fn(App $app): AttachRouteToRequest => new AttachRouteToRequest(
                $app->get(Router::class),
            ),
        );

        $app->set(
            DispatchRouteMiddleware::class,
            static fn(App $app): DispatchRouteMiddleware => new DispatchRouteMiddleware(
                $app->get(MiddlewareRequestHandlerFactory::class),
            ),
        );

        $app->set(
            DispatchRouteRequestHandler::class,
            static fn(App $app): DispatchRouteRequestHandler => new DispatchRouteRequestHandler(
                $app->get(RequestHandlerFactory::class),
            ),
        );

        $app->set(
            LogoutRequestHandler::class,
            static fn(App $app): LogoutRequestHandler => new LogoutRequestHandler(
                $app->get(SessionManager::class),
                $app->get(EventDispatcherInterface::class),
                $app->config->get('http.logout_redirect_url') ?? LogoutRequestHandler::DEFAULT_REDIRECT,
            ),
        );

        $app->set(StaticFileRequestHandler::class, NewInstanceServiceFactory::singleton());

        $app->set(SessionHandler::class, new SessionHandlerServiceFactory());

        $app->set(
            SessionManager::class,
            static fn(App $app): SessionManager => new SessionManager(
                $app->get(SessionHandler::class),
                $app->config->get('http.session'),
                $app->get(Natrium::class),
                $app->get(LoggerInterface::class),
            ),
        );

        $app->set(
            RateLimiter::class,
            static function (App $app): RateLimiter {
                $config = $app->get(HttpConfigStruct::class)->global_rate_limiting;
                if (! $config?->enabled || $config->rate_limiter_class === NullRateLimiter::class) {
                    return new NullRateLimiter(
                        $app->get(ClockInterface::class),
                        $app->get(EventDispatcherInterface::class),
                    );
                }

                if ($app->has(RedisManager::class) === false) {
                    return new NullRateLimiter(
                        $app->get(ClockInterface::class),
                        $app->get(EventDispatcherInterface::class),
                    );
                }

                $redis = $app->get(RedisManager::class)->connect();
                $rate_limiter_class = $config->rate_limiter_class;

                if ($rate_limiter_class === RedisRateLimiter::class) {
                    return new RedisRateLimiter(
                        $app->get(Redis::class),
                        $app->get(ClockInterface::class),
                        $app->get(EventDispatcherInterface::class),
                    );
                }

                return new $rate_limiter_class();
            },
        );

        $app->set(
            ThrottleRequests::class,
            static function (App $app): ThrottleRequests {
                $config = $app->config->get('http.rate_limiting');

                return new ThrottleRequests(
                    $app->get(RateLimiter::class),
                    $config->default_per_second,
                    $config->default_per_minute,
                );
            },
        );
    }
}
