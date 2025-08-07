<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache\Lock;

use PhoneBurner\Pinch\Component\Cache\Lock\NamedKey;
use Symfony\Component\Lock\Key;

use function PhoneBurner\Pinch\String\str_prefix;

final readonly class SymfonyNamedKey implements NamedKey
{
    public Key $key;

    public function __construct(public string $name)
    {
        $name || throw new \InvalidArgumentException('The name cannot be empty.');
        $this->key = new Key(str_prefix($name, 'locks.'));
    }

    #[\Override]
    public function __toString(): string
    {
        return 'named_key.' . $this->name;
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'key' => $this->key,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->key = $data['key'];
    }
}
