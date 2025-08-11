<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use FilesystemIterator;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Filesystem\FileWriter;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandler;
use PhoneBurner\Pinch\Random\Randomizer;
use PhoneBurner\Pinch\Time\Clock\Clock;
use PhoneBurner\Pinch\Time\Interval\TimeInterval;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

#[Internal]
final class FileSessionHandler extends SessionHandler
{
    public const string DEFAULT_STORAGE_PATH = APP_ROOT . '/storage/sessions';

    public function __construct(
        private readonly Clock $clock,
        private readonly TimeInterval $ttl,
        private readonly Randomizer $randomizer,
        private readonly string $path = self::DEFAULT_STORAGE_PATH,
    ) {
    }

    public function read(SessionId|string $id): string
    {
        // Since this implementation is not likely to be used in production, we
        // optimize for the other "self-garbage-collecting" implementations by
        // handling probabilistic garbage collection here. By default, at scale we
        // expect 2% of requests to trigger garbage collection.
        if ($this->randomizer->int(1, 100) <= 2) {
            $this->gc($this->ttl->seconds);
            \clearstatcache(); // prevents stale cached mtime/is_file affecting
        }

        $file = $this->getSessionFileInfo($id);
        if ($file->isFile() && $file->getMTime() >= $this->timestamp() - $this->ttl->seconds) {
            return (string)\file_get_contents($file->getPathname());
        }

        return '';
    }

    public function write(SessionId|string $id, string $data): bool
    {
        // We are using FileWriter to write the file to disk instead of something
        // like \file_put_contents, because it handles permissions checks and will
        // create parent directories if they do not already exist.
        return FileWriter::string($this->getSessionFileInfo($id), $data);
    }

    public function destroy(SessionId|string $id): bool
    {
        $file = $this->getSessionFileInfo($id);
        return $file->isFile() && \unlink($file->getPathname());
    }

    #[\Override]
    public function gc(int $max_lifetime): int
    {
        $deleted_sessions = 0;
        $expiration = $this->timestamp() - $max_lifetime;
        foreach (new FilesystemIterator($this->path) as $file) {
            \assert($file instanceof \SplFileInfo);
            if ($file->isFile() && $file->getMTime() < $expiration) {
                \unlink($file->getPathname());
                ++$deleted_sessions;
            }
        }

        return $deleted_sessions;
    }

    private function getSessionFileInfo(SessionId|string $id): \SplFileInfo
    {
        return new \SplFileInfo($this->path . '/' . $id);
    }

    private function timestamp(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
