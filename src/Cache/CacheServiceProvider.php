<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\App\ServiceFactory\NewInstanceServiceFactory;
use PhoneBurner\Pinch\Component\Cache\AppendOnly\AppendOnlyCacheAdapter;
use PhoneBurner\Pinch\Component\Cache\AppendOnlyCache;
use PhoneBurner\Pinch\Component\Cache\Cache;
use PhoneBurner\Pinch\Component\Cache\CacheAdapter;
use PhoneBurner\Pinch\Component\Cache\CacheDriver;
use PhoneBurner\Pinch\Component\Cache\InMemoryCache;
use PhoneBurner\Pinch\Component\Cache\Lock\LockFactory;
use PhoneBurner\Pinch\Component\Cache\Psr6\CacheItemPoolFactory as CacheItemPoolFactoryContract;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Configuration\Exception\InvalidConfiguration;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyLockFactoryAdapter;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyNamedKeyFactory;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Lock\LockFactory as SymfonyLockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Store\RedisStore;

use function PhoneBurner\Pinch\ghost;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class CacheServiceProvider implements DeferrableServiceProvider
{
    public static function provides(): array
    {
        return [
            AppendOnlyCache::class,
            Cache::class,
            InMemoryCache::class,
            CacheAdapter::class,
            CacheInterface::class,
            CacheItemPoolInterface::class,
            CacheItemPoolFactory::class,
            CacheItemPoolFactoryContract::class,
            SymfonyNamedKeyFactory::class,
            LockFactory::class,
            AppendOnlyCacheAdapter::class,
            SymfonyLockFactoryAdapter::class,
        ];
    }

    public static function bind(): array
    {
        return [
            Cache::class => CacheAdapter::class,
            CacheInterface::class => CacheAdapter::class,
            CacheItemPoolInterface::class => CacheAdapter::class,
            CacheItemPoolFactoryContract::class => CacheItemPoolFactory::class,
            AppendOnlyCache::class => AppendOnlyCacheAdapter::class,
            LockFactory::class => SymfonyLockFactoryAdapter::class,
        ];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->ghost(AppendOnlyCacheAdapter::class, static fn(AppendOnlyCacheAdapter $ghost): null => $ghost->__construct(
            $app->get(CacheItemPoolFactory::class)->make(CacheDriver::File),
        ));

        $app->ghost(CacheAdapter::class, static fn(CacheAdapter $ghost): null => $ghost->__construct(
            $app->get(CacheItemPoolFactory::class)->make(CacheDriver::Remote),
        ));

        $app->ghost(InMemoryCache::class, static fn(InMemoryCache $ghost): null => $ghost->__construct(
            $app->get(CacheItemPoolFactory::class)->make(CacheDriver::Memory),
        ));

        $app->ghost(CacheItemPoolFactory::class, static fn(CacheItemPoolFactory $ghost): null => $ghost->__construct(
            $app->environment,
            $app->get(RedisManager::class),
            $app->get(LoggerInterface::class),
        ));

        $app->set(SymfonyNamedKeyFactory::class, NewInstanceServiceFactory::singleton());

        $app->ghost(SymfonyLockFactoryAdapter::class, static function (SymfonyLockFactoryAdapter $ghost) use ($app): void {
            $store_driver = $app->config->get('cache.lock.store_driver');
            $store_driver = match (true) {
                $app->environment->context === Context::Test, $store_driver === InMemoryStore::class => InMemoryStore::class,
                $app->environment->stage === BuildStage::Production, $store_driver === RedisStore::class => RedisStore::class,
                default => throw new InvalidConfiguration('Invalid Cache Lock Store Driver'),
            };

            $ghost->__construct(
                $app->get(SymfonyNamedKeyFactory::class),
                new SymfonyLockFactory(match ($store_driver) {
                    InMemoryStore::class => new InMemoryStore(),
                    RedisStore::class => ghost(static fn(RedisStore $ghost): null => $ghost->__construct(
                        $app->get(RedisManager::class)->connect(),
                    )),
                }),
            );

            if ($app->environment->stage !== BuildStage::Production) {
                $ghost->setLogger($app->get(LoggerInterface::class));
            }
        });
    }
}
