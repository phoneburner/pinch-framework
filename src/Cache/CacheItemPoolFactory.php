<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache;

use PhoneBurner\Pinch\Component\Cache\CacheDriver;
use PhoneBurner\Pinch\Component\Cache\Psr6\CacheItemPoolFactory as CacheItemPoolFactoryContract;
use PhoneBurner\Pinch\Component\Cache\Psr6\CacheItemPoolProxy;
use PhoneBurner\Pinch\Component\Cache\Psr6\FileCacheItemPoolFactory as FileCacheItemPoolFactoryContract;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Framework\Cache\Marshaller\RemoteCacheMarshaller;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\String\Serialization\Serializer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

use function PhoneBurner\Pinch\Framework\path;
use function PhoneBurner\Pinch\ghost;

class CacheItemPoolFactory implements CacheItemPoolFactoryContract, FileCacheItemPoolFactoryContract
{
    public const string DEFAULT_NAMESPACE = 'cache';

    public const string DEFAULT_STATIC_CACHE_FILE = '/storage/bootstrap/static.cache.php';

    public const string DEFAULT_FILE_CACHE_DIRECTORY = '/storage/';

    private CacheItemPoolInterface|null $pool = null;

    private CacheItemPoolInterface|null $memory = null;

    private CacheItemPoolInterface|null $file = null;

    private CacheItemPoolInterface|null $null = null;

    /**
     * Note: we inject the `RedisManager` here instead of an instance of `\Redis`,
     * in order to potentially delay instantiating the Redis connection until
     * it is actually needed, if needed at all.
     */
    public function __construct(
        private readonly Environment $environment,
        private readonly RedisManager $redis_manager,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function make(CacheDriver $driver, string|null $namespace = null): CacheItemPoolInterface
    {
        return $namespace !== null ? new ProxyAdapter($this->make($driver), $namespace) : match ($driver) {
            CacheDriver::Remote => $this->pool ??= $this->createDefaultCacheItemPool(),
            CacheDriver::File => $this->file ??= $this->createFileCacheItemPool(
                self::DEFAULT_NAMESPACE,
                path(self::DEFAULT_FILE_CACHE_DIRECTORY),
            ),
            CacheDriver::Memory => $this->memory ??= new ArrayAdapter(storeSerialized: false),
            CacheDriver::None => $this->null ??= new NullAdapter(),
        };
    }

    public function createFileCacheItemPool(
        string $namespace = '',
        string|null $directory = null,
    ): CacheItemPoolInterface {
        if ($this->environment->context === Context::Test) {
            return $this->make(CacheDriver::Memory);
        }

        return ghost(static fn(PhpFilesAdapter $ghost): null => $ghost->__construct(
            $namespace,
            directory: $directory,
            appendOnly: true,
        ));
    }

    /**
     * Note: The external most cache adapter needs to either be the instance of
     * PhpArrayAdapter or a TraceableAdapter directly wrapping the PhpArrayAdapter.
     * The PhpArrayAdapter is the only adapter that can be used to warm up the cache
     * and the values are already held in memory, so we do not want to put anything
     * in front of it in production.
     *
     * Note: this ghosts a CacheItemPoolProxy instead of just returning
     * proxies because we need a concrete class to make lazy, and that's going
     * to be configuration driven.
     */
    private function createDefaultCacheItemPool(): CacheItemPoolInterface
    {
        if ($this->environment->context === Context::Test) {
            return $this->make(CacheDriver::Memory);
        }

        return ghost(function (CacheItemPoolProxy $ghost): void {
            $cache = new RedisAdapter(
                redis: $this->redis_manager->connect(),
                namespace: self::DEFAULT_NAMESPACE,
                marshaller: match ($this->environment->stage) {
                    BuildStage::Production, BuildStage::Integration => new RemoteCacheMarshaller(Serializer::Igbinary),
                    BuildStage::Development => new RemoteCacheMarshaller(
                        serializer: Serializer::Php,
                        compress: false,
                        throw_on_serialization_failure: true,
                        logger: $this->logger,
                    ),
                },
            );

            $cache = new ChainAdapter([new ArrayAdapter(storeSerialized: false), $cache]);
            $static_cache_path = path(self::DEFAULT_STATIC_CACHE_FILE);
            if (\file_exists($static_cache_path)) {
                $cache = new PhpArrayAdapter($static_cache_path, $cache);
            }

            $ghost->__construct($cache);
        });
    }
}
