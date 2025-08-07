<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

final readonly class DoctrineMigrationsConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    public function __construct(
        public array $table_storage = [
            'table_name' => 'doctrine_migration_versions',
        ],
        public array $migrations_paths = [
            'PhoneBurner\Pinch\Migrations' => APP_ROOT . '/src/Database/Migrations',
        ],
    ) {
    }
}
