<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Preload;

class PreloadCompiler
{
    public const string SYSLOG_FORMAT = "<%%d>1 %%s %s number-platform %s - - %%s\n";

    private int $count = 0;

    private readonly string $format;

    public function __construct(
        private readonly string $log_dir,
        private bool $debug = false,
        private array $compile_paths = [],
        private array $exclude_paths = [],
        private array $invalidate_paths = [],
    ) {
        $this->format = \sprintf(self::SYSLOG_FORMAT, \gethostname() ?: 'localhost', \getmypid() ?: 0);
        if (! \is_dir($log_dir) || ! \is_writable($log_dir)) {
            throw new \RuntimeException('Log directory does not exist or is not writable: ' . $log_dir);
        }
    }

    public function debug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Add a file or directory path for preload processing.
     */
    public function compile(string $path): self
    {
        $this->compile_paths[$path] = $path;
        return $this;
    }

    /**
     * Add a file path matcher string for excluding files from being preloaded,
     * using the format supported by the `\fnmatch()` function
     *
     * @see \fnmatch()
     * @link https://www.php.net/manual/en/function.fnmatch.php
     */
    public function exclude(string $fnmatch): self
    {
        $this->exclude_paths[$fnmatch] = $fnmatch;
        return $this;
    }

    /**
     * Add a file or directory path for invalidation. After the compilation step,
     * we'll invalidate the opcache for these files/directories, which we may not
     * want to be cached before runtime, e.g. config and cache files.
     */
    public function invalidate(string $path): self
    {
        $this->invalidate_paths[$path] = $path;
        return $this;
    }

    public function __invoke(): int
    {
        $this->count = 0;
        try {
            foreach ($this->compile_paths as $path) {
                match (true) {
                    \is_dir($path) => $this->doCompileDirectory($path),
                    \is_file($path) => $this->doCompileFile($path),
                    default => $this->log('The provided path is neither a file nor a directory: ' . $path, \LOG_WARNING),
                };
            }

            foreach ($this->invalidate_paths as $path) {
                match (true) {
                    \is_dir($path) => $this->doInvalidateDirectory($path),
                    \is_file($path) => $this->doInvalidateFile($path),
                    default => null, // assume it is ok for paths we might invalidate to not exist
                };
            }
        } catch (\Throwable $e) {
            $this->log("PRELOAD FAILED: " . $e->getMessage(), \LOG_ERR);
        }

        return $this->count;
    }

    private static function files(string $path): \Iterator
    {
        return new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS
            | \FilesystemIterator::KEY_AS_PATHNAME
            | \FilesystemIterator::CURRENT_AS_PATHNAME
            | \FilesystemIterator::FOLLOW_SYMLINKS));
    }

    private function doCompileDirectory(string $path): void
    {
        foreach (self::files($path) as $file) {
            if (\str_ends_with((string)$file, '.php')) {
                $this->doCompileFile($file);
            }
        }
    }

    private function doInvalidateDirectory(string $path): void
    {
        foreach (self::files($path) as $file) {
            $this->doInvalidateDirectory($file);
        }
    }

    private function doCompileFile(string $file): bool
    {
        try {
            return match (true) {
                $this->excluded($file) => $this->log(\sprintf('SKIP: %s (excluded)', $file), \LOG_DEBUG),
                @\opcache_is_script_cached($file) => $this->log(\sprintf('SKIP: %s (cached)', $file), \LOG_DEBUG),
                @\opcache_compile_file($file) && ++$this->count => $this->log('OK: ' . $file, \LOG_DEBUG),
                default => $this->log(\sprintf('SKIP: %s (failed)', $file), \LOG_WARNING),
            };
        } catch (\Throwable $e) {
            return $this->log(\sprintf("ERROR: %s (%s)", $file, $e->getMessage()), \LOG_ERR);
        }
    }

    private function doInvalidateFile(string $file): bool
    {
        if (@\opcache_is_script_cached($file)) {
            @\opcache_invalidate($file, true);
            return $this->log('INVALIDATE: ' . $file, \LOG_DEBUG);
        }
        return true;
    }

    private function excluded(string $file): bool
    {
        return \array_any($this->exclude_paths, static fn(string $path): bool => \fnmatch($path, $file));
    }

    private function log(string $message, int $severity): bool
    {
        return ($severity === \LOG_DEBUG && $this->debug === false) || \file_put_contents(
            \sprintf($this->log_dir . '/preload-%s.log', \date('Y-m-d')),
            \sprintf($this->format, 8 + $severity, \date('c'), $message),
            \FILE_APPEND,
        );
    }
}
