<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Storage\Config;

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\MimeTypeDetection\MimeTypeDetector;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructSerialization;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

final readonly class LocalFilesystemConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;
    use ConfigStructSerialization;

    /**
     * @param non-empty-string $location
     */
    public function __construct(
        public string $location = APP_ROOT . '/storage/app',
        public VisibilityConverter|null $visibility = null,
        public int $write_flags = \LOCK_EX,
        public int $link_handling = LocalFilesystemAdapter::DISALLOW_LINKS,
        public MimeTypeDetector|null $mime_type_detector = null,
        public bool $lazy_root_creation = false,
        public bool $use_inconclusive_mime_type_fallback = false,
    ) {
    }
}
