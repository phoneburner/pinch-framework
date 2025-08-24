<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Orm;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Tools\Console\ConnectionProvider as DoctrineConnectionProvider;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\Region\DefaultRegion;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration as EntityManagerConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\UnknownManagerException;
use PhoneBurner\Pinch\Component\Cache\CacheDriver;
use PhoneBurner\Pinch\Component\Cache\Psr6\CacheItemPoolFactory;
use PhoneBurner\Pinch\Component\Cache\Psr6\FileCacheItemPoolFactory;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Framework\Database\Config\DoctrineConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Config\DoctrineEntityManagerConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Cache\CacheRegion;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Cache\CacheType;
use PhoneBurner\Pinch\Framework\Database\Doctrine\ConnectionFactory;
use PhoneBurner\Pinch\Framework\Database\Doctrine\Types;
use Psr\Container\ContainerInterface;

use function PhoneBurner\Pinch\ghost;

use const PhoneBurner\Pinch\Time\SECONDS_IN_HOUR;

class EntityManagerFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Environment $environment,
        private readonly DoctrineConfigStruct $config,
        private readonly DoctrineConnectionProvider $connection_provider,
        private readonly CacheItemPoolFactory&FileCacheItemPoolFactory $cache_factory,
    ) {
    }

    /**
     * Returns a lazy ghost of the EntityManager
     *
     * To support namespaced annotations, we're not using the simple annotation driver
     *
     * Why are we globally ignoring parsing the "@mixin" annotations?
     *
     * Doctrine will recursively parse the annotations of vendor classes
     * that are used in creating the properties of entities while querying
     * the database. This can have unexpected consequences when Doctrine
     * and a third party vendor disagree on what is the "standard" for a
     * particular annotation, in this case the way that the Carbon library
     * uses the "@mixin" annotation is not compatible with the way that
     * the Doctrine Annotation library handles resolving class names. This
     * bug has been reported on the issue tracker for both libraries, but
     * both closed the issues as a "wont-fix" and claim the other library
     * is responsible to fix.
     *
     * Note: The even though the metadata, query, and entity caches may use the
     * append-only PHP file cache driver, we use separate instances in order to
     * clear each cache independently, and independent of the main append-only
     * cache pool.
     *
     * @link https://github.com/briannesbitt/Carbon/issues/2525
     * @link https://github.com/doctrine/annotations/pull/293
     */
    public function ghost(
        string $name = ConnectionFactory::DEFAULT,
    ): EntityManagerInterface {
        return ghost(function (EntityManager $em) use ($name): void {
            $this->registerCustomTypes();

            $config = $this->config->connections[$name]->entity_manager ?? throw UnknownManagerException::unknownManager(
                $name,
                \array_map(\strval(...), \array_keys($this->config->connections)),
            );
            \assert($config instanceof DoctrineEntityManagerConfigStruct);

            $cache_path = $config->cache_path ?? \sprintf("%s/doctrine/%s/", \sys_get_temp_dir(), $name);

            $doctrine_config = new EntityManagerConfiguration();
            $doctrine_config->setEntityListenerResolver(new EntityListenerContainerResolver($this->container));

            $doctrine_config->setProxyDir($cache_path . '/proxy');
            $doctrine_config->setProxyNamespace(\ucfirst($name) . 'DoctrineProxies');
            $doctrine_config->setAutoGenerateProxyClasses(match ($this->environment->stage) {
                BuildStage::Production => ProxyFactory::AUTOGENERATE_NEVER,
                default => ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED,
            });

            $doctrine_config->setMetadataDriverImpl(new AttributeDriver($config->entity_paths, true));
            $doctrine_config->setMetadataCache(match ($this->resolveCacheDriver(CacheType::Metadata, $config->metadata_cache_driver)) {
                CacheDriver::File => $this->cache_factory->createFileCacheItemPool(CacheType::Metadata->value, $cache_path),
                CacheDriver::Memory => $this->cache_factory->make(CacheDriver::Memory, \sprintf('orm.%s.metadata.', $name)),
                CacheDriver::None => $this->cache_factory->make(CacheDriver::None),
                default => throw new \LogicException('Unsupported Cache Type for Doctrine ORM Metadata Cache'),
            });

            $doctrine_config->setQueryCache(match ($this->resolveCacheDriver(CacheType::Query, $config->query_cache_driver)) {
                CacheDriver::File => $this->cache_factory->createFileCacheItemPool(CacheType::Query->value, $cache_path),
                CacheDriver::Memory => $this->cache_factory->make(CacheDriver::Memory, \sprintf('orm.%s.query.', $name)),
                CacheDriver::None => $this->cache_factory->make(CacheDriver::None),
                default => throw new \LogicException('Unsupported Cache Type for Doctrine ORM Query Cache'),
            });

            $doctrine_config->setResultCache(match ($this->resolveCacheDriver(CacheType::Result, $config->result_cache_driver)) {
                CacheDriver::Remote => $this->cache_factory->make(CacheDriver::Remote, \sprintf('orm.%s.result.', $name)),
                CacheDriver::Memory => $this->cache_factory->make(CacheDriver::Memory, \sprintf('orm.%s.result.', $name)),
                CacheDriver::None => $this->cache_factory->make(CacheDriver::None),
                default => throw new \LogicException('Unsupported Cache Type for Doctrine ORM Result Cache'),
            });

            $this->configureEntityCache($doctrine_config, $config->entity_cache_driver, $name, $cache_path);

            if ($config->mapped_field_types) {
                $doctrine_config->setTypedFieldMapper(new DefaultTypedFieldMapper($config->mapped_field_types));
            }

            $em->__construct($this->connection_provider->getConnection($name), $doctrine_config);

            foreach ($config->event_subscribers as $subscriber) {
                $subscriber = $this->container->get($subscriber);
                \assert($subscriber instanceof EventSubscriber);
                $em->getEventManager()->addEventSubscriber($subscriber);
            }
        });
    }

    /**
     * We need to register custom types before creating, but only once, so we use
     * a method-scoped static variable to track if we've already registered them.
     */
    private function registerCustomTypes(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        foreach ([...Types::REGISTRATION_MAP, ...$this->config->types] as $type_name => $class_name) {
            Type::addType((string)$type_name, $class_name);
        }

        $registered = true;
    }

    private function configureEntityCache(
        EntityManagerConfiguration $doctrine_config,
        CacheDriver|null $cache_driver,
        string $name,
        string $cache_path,
    ): void {
        $cache_driver = $this->resolveCacheDriver(CacheType::Entity, $cache_driver);
        if ($cache_driver === CacheDriver::None) {
            return;
        }

        $regions_config = new RegionsConfiguration(SECONDS_IN_HOUR);

        $factory = new DefaultCacheFactory($regions_config, match ($cache_driver) {
            CacheDriver::Remote => $this->cache_factory->make(CacheDriver::Remote, \sprintf('orm.%s.entity.', $name)),
            CacheDriver::Memory => $this->cache_factory->make(CacheDriver::Memory, \sprintf('orm.%s.entity.', $name)),
            CacheDriver::File => throw new \LogicException('Unsupported Cache Type for Doctrine ORM Entity Cache'),
        });

        $factory->setRegion(new DefaultRegion(CacheRegion::APPEND_ONLY, match ($cache_driver) {
            CacheDriver::File, CacheDriver::Remote => $this->cache_factory->createFileCacheItemPool(CacheType::Entity->value, $cache_path),
            CacheDriver::Memory => $this->cache_factory->make(CacheDriver::Memory, \sprintf('orm.%s.entity.', $name)),
        }));

        $doctrine_config->setSecondLevelCacheEnabled(true);
        $doctrine_config->getSecondLevelCacheConfiguration()?->setRegionsConfiguration($regions_config);
        $doctrine_config->getSecondLevelCacheConfiguration()?->setCacheFactory($factory);
    }

    private function resolveCacheDriver(CacheType $type, CacheDriver|null $cache_driver): CacheDriver
    {
        if ($this->environment->context === Context::Test) {
            return CacheDriver::Memory;
        }

        return $cache_driver ?? match ($type) {
            CacheType::Metadata, CacheType::Query => match ($this->environment->stage) {
                BuildStage::Production, BuildStage::Staging => CacheDriver::File,
                default => CacheDriver::Memory,
            },
            CacheType::Result, CacheType::Entity => match ($this->environment->stage) {
                BuildStage::Production, BuildStage::Staging => CacheDriver::Remote,
                default => CacheDriver::Memory,
            },
        };
    }
}
