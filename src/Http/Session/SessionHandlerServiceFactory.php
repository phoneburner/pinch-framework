<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session;

use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory;
use PhoneBurner\Pinch\Component\Cache\Lock\LockFactory;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Http\Cookie\CookieJar;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\Http\Config\SessionConfigStruct;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\CompressingSessionHandlerDecorator;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\CookieSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\EncodingSessionHandlerDecorator;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\EncryptingSessionHandlerDecorator;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\FileSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\InMemorySessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\LockingSessionHandlerDecorator;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\NullSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\RedisSessionHandler;
use PhoneBurner\Pinch\Random\Randomizer;
use PhoneBurner\Pinch\String\Encoding\Encoding;
use PhoneBurner\Pinch\Time\Clock\Clock;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\Type\narrow;

class SessionHandlerServiceFactory implements ServiceFactory
{
    public function __invoke(App $app, string $id): SessionHandler
    {
        $config = narrow(SessionConfigStruct::class, $app->config->get('http.session'));

        $handler = match ($config->handler->getSessionHandlerClass()->value) {
            RedisSessionHandler::class => ghost(static fn(RedisSessionHandler $ghost): null => $ghost->__construct(
                $app->get(RedisManager::class)->connect(),
                $config->ttl,
            )),
            FileSessionHandler::class => new FileSessionHandler(
                $app->get(Clock::class),
                $config->ttl,
                $app->get(Randomizer::class),
                $config->file_path,
            ),
            CookieSessionHandler::class => new CookieSessionHandler(
                $app->get(CookieJar::class),
                $config->ttl,
            ),
            InMemorySessionHandler::class => new InMemorySessionHandler(),
            NullSessionHandler::class => new NullSessionHandler(),
            default => throw new \LogicException('Undefined session handler type'),
        };

        $wrapped_handler = $handler;

        // Always encode the session data if the handler is CookieSessionHandler
        // This isn't necessary for other handlers that are binary safe like Redis
        if ($config->encoding || $handler instanceof CookieSessionHandler) {
            $wrapped_handler = new EncodingSessionHandlerDecorator(
                $wrapped_handler,
                $config->encoding ?? Encoding::Base64UrlNoPadding,
            );
        }

        // Always encrypt the session data if the handler is CookieSessionHandler
        if ($config->encrypt || $handler instanceof CookieSessionHandler) {
            $wrapped_handler = new EncryptingSessionHandlerDecorator(
                $wrapped_handler,
                $app->get(Natrium::class),
            );
        }

        // Always compress the session data if the handler is CookieSessionHandler
        if ($config->compress || $handler instanceof CookieSessionHandler) {
            $wrapped_handler = new CompressingSessionHandlerDecorator(
                $wrapped_handler,
            );
        }

        if ($config->lock_sessions) {
            return new LockingSessionHandlerDecorator(
                $wrapped_handler,
                $app->get(LockFactory::class),
            );
        }

        return $wrapped_handler;
    }
}
