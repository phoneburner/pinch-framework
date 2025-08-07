<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache\Config;

use PhoneBurner\Pinch\Component\Cache\CacheDriver;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;
use PhoneBurner\Pinch\String\Serialization\Serializer;
use Symfony\Component\Lock\Store\InMemoryStore;

final readonly class CacheConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public array $config = [
            'lock' => [
                'store_driver' => InMemoryStore::class,
            ],
            'drivers' => [
                CacheDriver::Remote->value => [
                    'serializer' => Serializer::Php,
                ],
                CacheDriver::File->value => [

                ],
                CacheDriver::Memory->value => [

                ],
            ],
        ],
    ) {
    }
}
