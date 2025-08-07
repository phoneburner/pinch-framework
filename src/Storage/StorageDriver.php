<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Storage;

/**
 * Note: this class is intentionally not an enum, since the end user may implement
 * their own storage driver or use different names for the same driver. (Breaking
 * the requirement for an enum to be a closed set of values.)
 */
final readonly class StorageDriver
{
    public const string LOCAL = 'local';
    public const string S3 = 's3';
}
