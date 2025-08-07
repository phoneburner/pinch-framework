<?php // phpcs:disable PSR1.Files.SideEffects

/**
 * This bootstrap file is loaded after the vendor autoload files, and after the
 * XML configuration file has been loaded, but before tests are run.
 */

declare(strict_types=1);

use PhoneBurner\Pinch\Component\Configuration\Context;

if (! \defined('PhoneBurner\Pinch\Framework\CONTEXT')) {
    \define('PhoneBurner\Pinch\Framework\CONTEXT', Context::Test);
}

if (! \defined('PhoneBurner\Pinch\Framework\UNIT_TEST_ROOT')) {
    \define('PhoneBurner\Pinch\Framework\UNIT_TEST_ROOT', __DIR__);
}
