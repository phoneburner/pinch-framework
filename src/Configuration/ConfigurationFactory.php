<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Configuration;

use PhoneBurner\Pinch\Component\Configuration\ConfigurationFactory as ConfigurationFactoryContract;
use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Component\Configuration\ImmutableConfiguration;
use PhoneBurner\Pinch\Framework\String\Serialization\SymfonyVarExporter;
use PhoneBurner\Pinch\String\Serialization\VarExporter;

use function PhoneBurner\Pinch\ghost;

/**
 * Important: for the sake of serializing the configuration as a PHP array, and
 * leveraging the performance we can get out of opcache keeping that static array
 * in memory, the values of the configuration MUST be limited to scalar types,
 * null, PHP enum cases (since those are just fancy class constants
 *  under the hood), and simple struct-like classes implementing arrays.
 */
class ConfigurationFactory implements ConfigurationFactoryContract
{
    public function __construct(
        private readonly VarExporter $var_exporter = new SymfonyVarExporter(),
    ) {
    }

    /**
     * @param string $config_dir_path relative to the root defined in $environment
     * @param string $cache_file_path relative to the root defined in $environment
     */
    public function make(
        Environment $environment,
        string $config_dir_path = ConfigurationFactoryContract::DEFAULT_CONFIG_PATH,
        string $cache_file_path = ConfigurationFactoryContract::DEFAULT_CACHE_FILE,
    ): ImmutableConfiguration {
        $factory = $this;
        return ghost(static fn(ImmutableConfiguration $ghost): null => $ghost->__construct($factory->load(
            (bool)$environment->env('PINCH_ENABLE_CONFIG_CACHE', true, false),
            $environment->root . $config_dir_path,
            $environment->root . $cache_file_path,
        )));
    }

    /**
     * @param string $config_dir_path relative to the root defined in $environment
     * @param string $cache_file_path relative to the root defined in $environment
     */
    private function load(
        bool $cache_enabled,
        string $config_dir_path = ConfigurationFactoryContract::DEFAULT_CONFIG_PATH,
        string $cache_file_path = ConfigurationFactoryContract::DEFAULT_CACHE_FILE,
    ): array {
        $cached = $cache_enabled ? self::cached($cache_file_path) : null;
        if ($cached !== null) {
            return $cached;
        }

        // If the cache file exists, including it above did not return a valid
        // PHP array, so we assume that the file is stale or corrupted. Delete
        // it and invalidate the opcache entry, so we can safely recompile.
        if (\file_exists($cache_file_path)) {
            @\unlink($cache_file_path);
            @\opcache_invalidate($cache_file_path, true);
        }

        $config = self::compile($config_dir_path);
        if ($cache_enabled) {
            $this->var_exporter->file($cache_file_path, $config, 'Configuration Cache');
        }

        return $config;
    }

    /**
     * Note: we're intentionally skipping a \file_exists() check here, as we
     * expect opcache to handle this for us, and since the file to usually
     * exist in production, there's a minor performance gain.
     *
     * @phpstan-ignore include.fileNotFound (see https://github.com/phpstan/phpstan/issues/11798)
     */
    public static function cached(string $cache_file_path): array|null
    {
        try {
            $cached_config = @include $cache_file_path;
            return \is_array($cached_config) ? $cached_config : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function compile(string $config_dir_path): array
    {
        $config = [];
        foreach (\glob(\sprintf("%s/*.php", $config_dir_path)) ?: [] as $file) {
            foreach (include $file ?: [] as $key => $value) {
                $config[$key] = $value;
            }
        }
        return $config;
    }
}
