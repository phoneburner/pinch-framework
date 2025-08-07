<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Configuration;

use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Framework\App\App;
use PhoneBurner\Pinch\Framework\App\ErrorReporting;
use PhoneBurner\Pinch\Framework\Configuration\Exception\EnvironmentInitializationFailed;

use function PhoneBurner\Pinch\Array\array_any_value;

class EnvironmentLoader
{
    private const array ARGON2_OPTIONS_DEFAULT = [
        'memory_cost' => \PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        'time_cost' => \PASSWORD_ARGON2_DEFAULT_TIME_COST,
        'thread_cost' => \PASSWORD_ARGON2_DEFAULT_THREADS,
    ];

    private const array ARGON2_OPTIONS_TEST = [
        'memory_cost' => 8,
        'time_cost' => 1,
        'thread_cost' => 1,
    ];

    private const array TEST_ENV_CONSTANTS = [
        'PHPUNIT_COMPOSER_INSTALL',
        'BEHAT_BIN_PATH',
    ];

    private static Environment|null $environment = null;

    public static function instance(): Environment
    {
        return self::$environment ?? throw EnvironmentInitializationFailed::withUninitalizedState();
    }

    public static function init(string $app_root = ''): Environment
    {
        if (self::$environment !== null) {
            return self::$environment;
        }

        // Ensure that request timestamp values are set in the $_SERVER superglobal.
        $_SERVER['REQUEST_TIME'] ??= \time();
        $_SERVER['REQUEST_TIME_FLOAT'] ??= \microtime(true);

        self::$environment = new Environment(
            self::resolveContext(),
            self::resolveBuildStage(),
            self::resolveAppRoot($app_root),
            $_SERVER,
            $_ENV,
        );

        // Override the error reporting settings based on the environment configuration.
        if (self::$environment->stage !== BuildStage::Production) {
            ErrorReporting::override($_ENV);
        }

        // Define the password hashing options for Argon2 in test environments.
        // These options are less resource-intensive to speed up tests.
        \define('PhoneBurner\Pinch\Framework\PASSWORD_ARGON2_OPTIONS', match (self::$environment->context) {
            Context::Test => self::ARGON2_OPTIONS_TEST,
            default => self::ARGON2_OPTIONS_DEFAULT,
        });

        // Register the application lifecycle teardown method as a shutdown function so
        // that we can ensure that it is called when the script ends, regardless of how
        // it ends, including calls to exit().
        \register_shutdown_function(App::teardown(...));

        // Define a function that will be called when an undefined class is encountered
        // during deserialization, instead of returning a __PHP_Incomplete_Class object.
        // Note that we have to define this function early and cannot define with the
        // other functions in src/functions.php, which are loaded after this file.
        \assert(\function_exists('\PhoneBurner\Pinch\Framework\fail_on_unserialize_undefined_class'));
        /** @phpstan-ignore deadCode.unreachable (todo: this is most likely a bug in PHPStan )*/
        \ini_set('unserialize_callback_func', '\PhoneBurner\Pinch\Framework\fail_on_unserialize_undefined_class');

        return self::$environment;
    }

    private static function resolveAppRoot(string $app_root): string
    {
        if (! \is_dir($app_root)) {
            throw EnvironmentInitializationFailed::withInvalidAppRoot($app_root);
        }

        // Define the application root and web root paths as constants. A lot of
        // the framework code relies on these constants for defining relative and
        // absolute paths, so we need to ensure they are defined early.
        \define('PhoneBurner\Pinch\Framework\APP_ROOT', $app_root);
        \define('PhoneBurner\Pinch\Framework\WEB_ROOT', $app_root . '/public');

        return $app_root;
    }

    private static function resolveContext(): Context
    {
        // Context may already be set if we're running in a test environment
        if (\defined('PhoneBurner\Pinch\Framework\CONTEXT')) {
            return \constant('PhoneBurner\Pinch\Framework\CONTEXT');
        }

        // Check if we're running in a test environment, which is determined by the presence
        // of certain constants that are typically defined by PHPUnit or Behat.
        // Otherwise, match on the PHP SAPI to determine the context (note that this we use
        // string literals separate from the test constant check here so that PHP optimizes
        // this to a `O(1)` C jump table). We also have to use the array_any_value() function
        // instead of `\array_any()` because `defined()` is strict about the number
        // of arguments it accepts.
        $context = array_any_value(self::TEST_ENV_CONSTANTS, \defined(...)) ? Context::Test : match (\PHP_SAPI) {
            'fpm-fcgi', 'cgi-fcgi', 'cli-server', 'apache2handler', 'apache', => Context::Http,
            'cli', 'phpdbg' => Context::Cli,
            default => throw EnvironmentInitializationFailed::withUnsupportedContext(\PHP_SAPI),
        };

        \define('PhoneBurner\Pinch\Framework\CONTEXT', $context);

        return $context;
    }

    private static function resolveBuildStage(): BuildStage
    {
        // Make sure that the build stage is defined and set the same on $_SERVER and $_ENV,
        // If not explicitly set, default to production, but if one is set, it must be a
        // valid build stage.
        $value = $_SERVER['PINCH_BUILD_STAGE'] ?? $_ENV['PINCH_BUILD_STAGE'] ?? null;
        $stage = $value === null ? BuildStage::Production : BuildStage::parse($value);
        $stage ??= throw EnvironmentInitializationFailed::withUnsupportedBuildStage($value);

        // Normalize the values in both $_SERVER and $_ENV to the build stage value.
        $_SERVER['PINCH_BUILD_STAGE'] = $stage->value;
        $_ENV['PINCH_BUILD_STAGE'] = $stage->value;
        \define('PhoneBurner\Pinch\Framework\BUILD_STAGE', $stage);

        return $stage;
    }
}
