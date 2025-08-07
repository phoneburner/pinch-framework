<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Handler;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Http\Session\SessionId;
use PhoneBurner\Pinch\Framework\Http\Session\SessionHandler;

#[Internal]
final class NullSessionHandler extends SessionHandler
{
    public function read(string|SessionId $id): string
    {
        throw new \LogicException();
    }

    public function write(string|SessionId $id, string $data): bool
    {
        throw new \LogicException();
    }

    public function destroy(string|SessionId $id): bool
    {
        throw new \LogicException();
    }
}
