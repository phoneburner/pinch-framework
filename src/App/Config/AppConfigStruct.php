<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\Config;

use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Component\Configuration\Struct\ConfigStructArrayAccess;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\AsymmetricAlgorithm;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SharedKey;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SymmetricAlgorithm;
use PhoneBurner\Pinch\Component\I18n\IsoLocale;
use PhoneBurner\Pinch\Time\TimeZone\Tz;

class AppConfigStruct implements ConfigStruct
{
    use ConfigStructArrayAccess;

    public function __construct(
        public string $name,
        #[\SensitiveParameter] public SharedKey|null $key,
        public Tz $timezone = Tz::Utc,
        public IsoLocale $locale = IsoLocale::EN_US,
        public SymmetricAlgorithm $symmetric_algorithm = SymmetricAlgorithm::Aegis256,
        public AsymmetricAlgorithm $asymmetric_algorithm = AsymmetricAlgorithm::X25519Aegis256,
    ) {
    }

    public function __serialize(): array
    {
        return [
            $this->name,
            $this->key?->export(),
            $this->timezone,
            $this->locale,
            $this->symmetric_algorithm,
            $this->asymmetric_algorithm,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->__construct(
            $data[0],
            $data[1] ? SharedKey::import($data[1]) : null,
            $data[2],
            $data[3],
            $data[4],
            $data[5],
        );
    }
}
