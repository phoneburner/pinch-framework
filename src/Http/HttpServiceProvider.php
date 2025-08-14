<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\App\ServiceFactory\NewInstanceServiceFactory;
use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\KeyChain;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Http\Cookie\CookieJar;
use PhoneBurner\Pinch\Component\Http\Message\RequestSerializer;
use PhoneBurner\Pinch\Component\Http\Message\ResponseSerializer;
use PhoneBurner\Pinch\Component\Http\MessageSignature\HttpMessageSignatureFactory as HttpMessageSignatureFactoryContract;
use PhoneBurner\Pinch\Component\Http\Middleware\LazyMiddlewareRequestHandlerFactory;
use PhoneBurner\Pinch\Component\Http\Middleware\MiddlewareRequestHandlerFactory;
use PhoneBurner\Pinch\Component\Http\Middleware\NullThrottleRequests;
use PhoneBurner\Pinch\Component\Http\Middleware\ThrottleRequests;
use PhoneBurner\Pinch\Component\Http\RateLimiter\NullRateLimiter;
use PhoneBurner\Pinch\Component\Http\RateLimiter\RateLimiter;
use PhoneBurner\Pinch\Component\Http\Request\RequestFactory as RequestFactoryContract;
use PhoneBurner\Pinch\Component\Http\Request\RequestHandlerFactory;
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
use PhoneBurner\Pinch\Framework\Http\Config\HttpConfigStruct;
use PhoneBurner\Pinch\Framework\Http\Cookie\CookieEncrypter;
use PhoneBurner\Pinch\Framework\Http\Cookie\Middleware\ManageCookies;
use PhoneBurner\Pinch\Framework\Http\Emitter\MappingEmitter;
use PhoneBurner\Pinch\Framework\Http\EventListener\WriteSerializedRequestToFile;
use PhoneBurner\Pinch\Framework\Http\EventListener\WriteSerializedResponseToFile;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureFactory;
use PhoneBurner\Pinch\Framework\Http\Middleware\CatchExceptionalResponses;
use PhoneBurner\Pinch\Framework\Http\Middleware\TransformHttpExceptionResponses;
use PhoneBurner\Pinch\Framework\Http\RateLimiter\RedisRateLimiter;
use PhoneBurner\Pinch\Framework\Http\Request\RequestFactory;
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
            RequestSerializer::class,
            ResponseSerializer::class,
            WriteSerializedRequestToFile::class,
            WriteSerializedResponseToFile::class,
        ];
    }

    public static function bind(): array
    {
        return [
            Router::class => FastRouter::class,
            SessionManagerContract::class => SessionManager::class,
            RateLimiter::class => RedisRateLimiter::class,
            RequestFactoryContract::class => RequestFactory::class,
            HttpMessageSignatureFactoryContract::class => HttpMessageSignatureFactory::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->set(RequestSerializer::class, NewInstanceServiceFactory::singleton());
        $app->set(ResponseSerializer::class, NewInstanceServiceFactory::singleton());
        $app->set(JsonResponseTransformerStrategy::class, NewInstanceServiceFactory::singleton());
        $app->set(HtmlResponseTransformerStrategy::class, NewInstanceServiceFactory::singleton());
        $app->set(TextResponseTransformerStrategy::class, NewInstanceServiceFactory::singleton());
        $app->set(ErrorRequestHandler::class, NewInstanceServiceFactory::singleton());
        $app->set(FastRouteResultFactory::class, NewInstanceServiceFactory::singleton());
        $app->set(StaticFileRequestHandler::class, NewInstanceServiceFactory::singleton());

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
                    $app->get(HttpConfigStruct::class)->routing->fallback_handler ?: NotFoundRequestHandler::class,
                )),
                $app->get(HttpConfigStruct::class)->middleware,
            ),
        );

        $app->set(
            TransformHttpExceptionResponses::class,
            static fn(App $app): TransformHttpExceptionResponses => new TransformHttpExceptionResponses(
                $app->get(LogTrace::class),
                $app->get(HttpConfigStruct::class)->exceptional_response_default_transformer ?: TextResponseTransformerStrategy::class,
            ),
        );

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
                $app->get(HttpConfigStruct::class)->routing,
            ),
        );

        $app->set(
            DefinitionList::class,
            static fn(App $app): DefinitionList => LazyConfigDefinitionList::makeFromCallable(...\array_map(
                static fn(string $provider): RouteProvider => narrow(RouteProvider::class, new $provider()),
                $app->get(HttpConfigStruct::class)->routing->route_providers ?? [],
            )),
        );

        $app->set(
            ListRoutesCommand::class,
            static fn(App $app): ListRoutesCommand => new ListRoutesCommand(
                $app->get(DefinitionList::class),
            ),
        );

        $app->set(
            CacheRoutesCommand::class,
            ghost(static fn(CacheRoutesCommand $ghost): null => $ghost->__construct(
                $app->config,
                $app->get(FastRouter::class),
            )),
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
                $app->get(HttpConfigStruct::class)->logout_redirect_url ?: LogoutRequestHandler::DEFAULT_REDIRECT,
            ),
        );

        $app->set(SessionHandler::class, new SessionHandlerServiceFactory());

        $app->set(
            SessionManager::class,
            ghost(static fn(SessionManager $ghost): null => $ghost->__construct(
                $app->get(SessionHandler::class),
                $app->get(HttpConfigStruct::class)->session,
                $app->get(Natrium::class),
                $app->get(LoggerInterface::class),
            )),
        );

        $app->set(
            RateLimiter::class,
            static function (App $app): RateLimiter {
                $config = $app->get(HttpConfigStruct::class)->global_rate_limiting;
                $rate_limiter_class = $config?->enabled ? $config->rate_limiter_class : NullRateLimiter::class;

                return match ($rate_limiter_class) {
                    NullRateLimiter::class => ghost(static fn(NullRateLimiter $ghost): null => $ghost->__construct(
                        $app->get(Clock::class),
                        $app->get(EventDispatcherInterface::class),
                    )),
                    RedisRateLimiter::class => ghost(static fn(RedisRateLimiter $ghost): null => $ghost->__construct(
                        $app->get(Redis::class),
                        $app->get(Clock::class),
                        $app->get(EventDispatcherInterface::class),
                    )),
                    default => $app->get($rate_limiter_class),
                };
            },
        );

        $app->set(
            ThrottleRequests::class,
            static function (App $app): ThrottleRequests {
                $config = $app->get(HttpConfigStruct::class)->global_rate_limiting;
                if ($config === null || $config->enabled === false) {
                    return new NullThrottleRequests();
                }

                return new ThrottleRequests(
                    $app->get(RateLimiter::class),
                    $config->default_per_second_max,
                    $config->default_per_minute_max,
                );
            },
        );

        $app->set(
            WriteSerializedRequestToFile::class,
            ghost(static fn(WriteSerializedRequestToFile $ghost): null => $ghost->__construct(
                $app->get(RequestSerializer::class),
                $app->get(LogTrace::class),
                $app->get(LoggerInterface::class),
            )),
        );

        $app->set(
            WriteSerializedResponseToFile::class,
            ghost(static fn(WriteSerializedResponseToFile $ghost): null => $ghost->__construct(
                $app->get(ResponseSerializer::class),
                $app->get(LogTrace::class),
                $app->get(LoggerInterface::class),
            )),
        );

        $app->set(
            HttpMessageSignatureFactory::class,
            ghost(static fn(HttpMessageSignatureFactory $ghost): null => $ghost->__construct(
                $app->get(Natrium::class),
                $app->get(KeyChain::class),
                $app->get(Clock::class),
            )),
        );
    }
}
