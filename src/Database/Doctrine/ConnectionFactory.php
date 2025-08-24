<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine;

use Doctrine\DBAL\Configuration as ConnectionConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Tools\Console\ConnectionNotFound;
use PhoneBurner\Pinch\Component\Cache\CacheDriver;
use PhoneBurner\Pinch\Component\Cache\Psr6\CacheItemPoolFactory;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Framework\Database\Config\DoctrineConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Config\DoctrineConnectionConfigStruct;
use Psr\Log\LoggerInterface;

class ConnectionFactory
{
    public const string DEFAULT = 'default';

    public function __construct(
        private readonly Environment $environment,
        private readonly DoctrineConfigStruct $config,
        private readonly CacheItemPoolFactory $cache_factory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function connect(string $name = self::DEFAULT): Connection
    {
        $config = $this->config->connections[$name] ?? throw new ConnectionNotFound(
            'Connection Not Defined In Configuration: ' . $name,
        );

        \assert($config instanceof DoctrineConnectionConfigStruct);

        $connection_config = new ConnectionConfiguration();
        $connection_config->setResultCache(match ($this->resolveCacheDriver($config->result_cache_driver)) {
            CacheDriver::Remote => $this->cache_factory->make(CacheDriver::Remote, \sprintf('dbal.%s.result.', $name)),
            CacheDriver::Memory => $this->cache_factory->make(CacheDriver::Memory, \sprintf('dbal.%s.result.', $name)),
            CacheDriver::None => $this->cache_factory->make(CacheDriver::None),
            default => throw new \LogicException('Unsupported Cache Type for Doctrine DBAL Result Cache'),
        });

        if ($config->enable_logging) {
            $middleware = $connection_config->getMiddlewares();
            $middleware[] = new Middleware($this->logger);
            $connection_config->setMiddlewares($middleware);
        }

        \assert(\in_array($config->driver, DriverManager::getAvailableDrivers(), true));

        /** @phpstan-ignore-next-line Version Lag with PHPStan-Doctrine Has Mismatch in Expected Array Shape */
        return DriverManager::getConnection([
            'host' => $config->host,
            'port' => $config->port,
            'dbname' => $config->dbname,
            'user' => $config->user,
            'password' => $config->password,
            'driver' => $config->driver,
            'charset' => $config->charset,
            'driverOptions' => $config->driver_options,
            'serverVersion' => $config->server_version,
        ], $connection_config);
    }

    private function resolveCacheDriver(CacheDriver|null $cache_driver): CacheDriver
    {
        if ($this->environment->context === Context::Test) {
            return CacheDriver::Memory;
        }

        return $cache_driver ?? match ($this->environment->stage) {
            BuildStage::Production, BuildStage::Staging => CacheDriver::Remote,
            default => CacheDriver::Memory,
        };
    }
}
