<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Exception\InvalidConfiguration;
use PhoneBurner\Pinch\Framework\Storage\Config\StorageConfigStruct;

class FilesystemOperatorFactory
{
    private array $cache = [];

    public function __construct(
        private readonly StorageConfigStruct $configuration,
        private readonly FilesystemAdapterFactory $adapter_factory,
    ) {
    }

    public function make(string $driver): FilesystemOperator
    {
        return $this->cache[$driver] ??= $this->createFilesystemOperator($driver);
    }

    public function default(): FilesystemOperator
    {
        return $this->cache['default'] ??= $this->make($this->configuration->default);
    }

    private function createFilesystemOperator(string $driver): FilesystemOperator
    {
        $config = $this->configuration->drivers[$driver] ?? null;
        if (! $config instanceof ConfigStruct) {
            throw new InvalidConfiguration('No configuration defined for driver: ' . $driver);
        }

        return new Filesystem($this->adapter_factory->make($config));
    }
}
