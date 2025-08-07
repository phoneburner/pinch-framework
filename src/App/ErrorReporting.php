<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App;

class ErrorReporting
{
    public const int ALL_ERRORS = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR;
    public const int ALL_WARNINGS = \E_WARNING | \E_CORE_WARNING | \E_COMPILE_WARNING | \E_USER_WARNING;
    public const int ALL_NOTICES = \E_NOTICE | \E_USER_NOTICE;
    public const int ALL_DEPRECATIONS = \E_DEPRECATED | \E_USER_DEPRECATED;

    /**
     * Safely override the existing runtime error reporting level configuration.
     * The reported levels can only increase from the current level.
     */
    public static function override(array $env, PhpRuntimeConfig $config = new PhpRuntimeConfig()): void
    {
        $config->set('error_reporting', (string)((int)$config->get('error_reporting')
            | (self::cast($env, 'PINCH_ENABLE_REPORTING_ERRORS') ? self::ALL_ERRORS : 0)
            | (self::cast($env, 'PINCH_ENABLE_REPORTING_WARNINGS') ? self::ALL_WARNINGS : 0)
            | (self::cast($env, 'PINCH_ENABLE_REPORTING_NOTICES') ? self::ALL_NOTICES : 0)
            | (self::cast($env, 'PINCH_ENABLE_REPORTING_DEPRECATIONS') ? self::ALL_DEPRECATIONS : 0)));
    }

    private static function cast(array $env, string $key): bool
    {
        return \filter_var($env[$key] ?? null, \FILTER_VALIDATE_BOOL);
    }
}
