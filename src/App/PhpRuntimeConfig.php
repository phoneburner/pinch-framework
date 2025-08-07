<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App;

class PhpRuntimeConfig
{
    /**
     * Set a PHP INI configuration value, returning the **old** value as a string
     * on success, and false on failure.
     *
     * @return string|false
     */
    public function set(string $option, string $value): string|bool
    {
        return \ini_set($option, $value);
    }

    /**
     * Gets the value of a INI configuration option, returning the value as a
     * string on success, or an empty string for null values. Returns false if
     * the configuration option doesn't exist.
     *
     * @return string|false
     */
    public function get(string $option): string|bool
    {
        return \ini_get($option);
    }
}
