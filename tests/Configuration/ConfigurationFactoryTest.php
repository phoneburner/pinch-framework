<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Configuration;

use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Component\Configuration\ImmutableConfiguration;
use PhoneBurner\Pinch\Framework\Configuration\ConfigurationFactory;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\MockEnvironment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigurationFactoryTest extends TestCase
{
    private Environment $environment;
    private \SplFileInfo $root_dir;
    private \SplFileInfo $config_dir;
    private \SplFileInfo $cache_file;

    protected function setUp(): void
    {
        $this->root_dir = new \SplFileInfo(\sys_get_temp_dir() . '/pinch-test-' . \random_int(100_000, 999_999));
        \mkdir($this->root_dir->getPathname(), 0777, true);

        $this->config_dir = new \SplFileInfo($this->root_dir->getPathname() . '/config');
        \mkdir($this->config_dir->getPathname(), 0777, true);

        $this->cache_file = new \SplFileInfo($this->root_dir->getPathname() . '/storage/bootstrap/config.cache.php');
        \mkdir(\dirname($this->cache_file->getPathname()), 0777, true);

        $this->environment = new MockEnvironment($this->root_dir->getPathname(), env: ['PINCH_ENABLE_CONFIG_CACHE' => 'true']);
    }

    protected function tearDown(): void
    {
        @\unlink($this->cache_file->getPathname());
        foreach ([$this->config_dir, $this->root_dir] as $dir) {
            if (\is_dir($this->config_dir->getPathname())) {
                foreach (\glob($this->config_dir->getPathname() . '/*') ?: [] as $file) {
                    if (\is_file($file)) {
                        @\unlink($file);
                    }
                }
                @\rmdir($this->config_dir->getPathname());
            }
        }
    }

    #[Test]
    public function makeReturnsImmutableConfiguration(): void
    {
        $config = new ConfigurationFactory()->make($this->environment);
        self::assertInstanceOf(ImmutableConfiguration::class, $config);
    }

    #[Test]
    public function makeLoadsConfigurationFromFiles(): void
    {
        $config_file = $this->config_dir->getPathname() . '/test.php';
        \file_put_contents($config_file, '<?php return ["foo" => "bar"];');

        $config = new ConfigurationFactory()->make($this->environment);
        self::assertSame('bar', $config->get('foo'));
    }

    #[Test]
    public function makeMergesConfigurationFromMultipleFiles(): void
    {
        \file_put_contents($this->config_dir->getPathname() . '/first.php', '<?php return ["foo" => "bar"];');
        \file_put_contents($this->config_dir->getPathname() . '/second.php', '<?php return ["baz" => "qux"];');

        $config = new ConfigurationFactory()->make($this->environment);
        self::assertSame('bar', $config->get('foo'));
        self::assertSame('qux', $config->get('baz'));
    }

    #[Test]
    public function makeUsesCachedConfigurationWhenEnabled(): void
    {
        $cached_config = ['foo' => 'bar'];
        \file_put_contents($this->cache_file->getPathname(), '<?php return ' . \var_export($cached_config, true) . ';');

        $config = new ConfigurationFactory()->make($this->environment);
        self::assertSame('bar', $config->get('foo'));
    }

    #[Test]
    public function makeRegeneratesCacheWhenDisabled(): void
    {
        $environment = new MockEnvironment($this->root_dir->getPathname(), env: ['PINCH_ENABLE_CONFIG_CACHE' => 'false']);

        $cached_config = ['foo' => 'bar'];
        \file_put_contents($this->cache_file->getPathname(), '<?php return ' . \var_export($cached_config, true) . ';');

        \file_put_contents($this->config_dir . '/test.php', '<?php return ["baz" => "qux"];');

        $config = new ConfigurationFactory()->make($environment);
        self::assertNull($config->get('foo'));
        self::assertSame('qux', $config->get('baz'));
    }

    #[Test]
    public function makeRegeneratesCacheWhenInvalid(): void
    {
        \file_put_contents($this->cache_file->getPathname(), '<?php return "invalid";');

        \file_put_contents($this->config_dir . '/test.php', '<?php return ["foo" => "bar"];');

        $config = new ConfigurationFactory()->make($this->environment);
        self::assertSame('bar', $config->get('foo'));
    }
}
