<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework;

use PhoneBurner\Pinch\Framework\App\App;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

function app(): App
{
    return App::instance();
}

/**
 * Get an environment variable allowing for default.
 * Note: this has slightly different behavior from SALT, as it does not check $_SERVER
 * or fall back to getenv() if the variable is not set in $_ENV. The Pinch
 * Framework assumes that all environment variables are set in $_ENV.
 */
function env(
    string $key,
    mixed $production = null,
    mixed $development = null,
    mixed $integration = null,
): mixed {
    return App::instance()->environment->env($key, $production, $development, $integration);
}

function stage(
    mixed $production = null,
    mixed $development = null,
    mixed $integration = null,
): mixed {
    return App::instance()->environment->match($production, $development, $integration);
}

/**
 * Get full path relative to the application root
 *
 * @return non-empty-string
 */
function path(string $path): string
{
    \assert(\defined('\PhoneBurner\Pinch\Framework\APP_ROOT'), 'APP_ROOT must be defined');
    \assert(APP_ROOT !== '', 'APP_ROOT must not be empty');
    return APP_ROOT . $path;
}

// Define a function that will be called when an undefined class is encountered
// during deserialization, instead of returning a __PHP_Incomplete_Class object.
// Note that we have to define this function early and cannot define with the
// other functions in src/functions.php, which are loaded after this file.
function fail_on_unserialize_undefined_class(string $class): never
{
    throw new \DomainException('Class not found: ' . $class);
}
