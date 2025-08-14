<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\AsymmetricAlgorithm;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SharedKey;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SymmetricAlgorithm;
use PhoneBurner\Pinch\Component\I18n\IsoLocale;
use PhoneBurner\Pinch\Time\TimeZone\Tz;

interface AppConfigStruct extends ConfigStruct
{
    // phpcs:disable
    public string $name { get; }
    // phpcs:enable

    // phpcs:disable
    public SharedKey|null $key { get; }
    // phpcs:enable

    // phpcs:disable
    public Tz $timezone { get; }
    // phpcs:enable

    // phpcs:disable
    public IsoLocale $locale { get; }
    // phpcs:enable

    // phpcs:disable
    public SymmetricAlgorithm $symmetric_algorithm { get; }
    // phpcs:enable

    // phpcs:disable
    public AsymmetricAlgorithm $asymmetric_algorithm { get; }
    // phpcs:enable
}
