<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Storage\FilesystemAdapterFactory;

use League\Flysystem\FilesystemAdapter;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Framework\Storage\Config\LocalFilesystemConfigStruct;
use PhoneBurner\Pinch\Framework\Storage\Config\S3FilesystemConfigStruct;
use PhoneBurner\Pinch\Framework\Storage\FilesystemAdapterFactory;
use Psr\Container\ContainerInterface;

class ContainerAdapterFactory implements FilesystemAdapterFactory
{
    public const array DEFAULT_FACTORIES = [
        LocalFilesystemConfigStruct::class => LocalFilesystemAdapterFactory::class,
        S3FilesystemConfigStruct::class => S3FilesystemAdapterFactory::class,
    ];

    /**
     * @var array<class-string<ConfigStruct>, class-string<FilesystemAdapterFactory>>
     */
    private readonly array $factories;

    /**
     * @param array<class-string<ConfigStruct>, class-string<FilesystemAdapterFactory>> $custom_factories
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $custom_factories = [],
    ) {
        $this->factories = \array_merge(self::DEFAULT_FACTORIES, $this->custom_factories);
    }

    public function make(ConfigStruct $config): FilesystemAdapter
    {
        $factory_class = $this->factories[$config::class] ?? throw new \UnexpectedValueException(
            'No Factory Defined for Class: ' . $config::class,
        );

        return $this->container->get($factory_class)->make($config);
    }
}
