<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Storage;

use League\Flysystem\FilesystemAdapter;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;

interface FilesystemAdapterFactory
{
    public function make(ConfigStruct $config): FilesystemAdapter;
}
