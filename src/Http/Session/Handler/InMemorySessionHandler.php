<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandler;

#[Internal]
class InMemorySessionHandler extends SessionHandler
{
    private array $sessions = [];

    public function read(string|SessionId $id): string
    {
        return $this->sessions[(string)$id] ?? '';
    }

    public function write(string|SessionId $id, string $data): bool
    {
        $this->sessions[(string)$id] = $data;
        return true;
    }

    public function destroy(string|SessionId $id): bool
    {
        unset($this->sessions[(string)$id]);
        return true;
    }
}
