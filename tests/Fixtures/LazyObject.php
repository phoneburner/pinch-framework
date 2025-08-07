<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures;

class LazyObject
{
    public function __construct(private readonly \Closure $initializer)
    {
    }

    public function call(): mixed
    {
        return ($this->initializer)();
    }
}
