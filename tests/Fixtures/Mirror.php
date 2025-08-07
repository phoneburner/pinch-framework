<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures;

class Mirror extends ShinyThing implements ReflectsLightWaves
{
    public const int RED = 1;

    public const int BLUE = 2;

    public const int GREEN = 3;

    protected const string YELLOW = 'this is protected';

    /**
     * @phpstan-ignore classConstant.unused
     */
    private const string PURPLE = 'this is private';

    private string $foo = 'foobar';
    private int $bar = 7654321;

    public function getFoo(): string
    {
        return $this->foo;
    }

    /**
     * @phpstan-ignore method.unused
     */
    private function getBar(): int
    {
        return $this->bar;
    }
}
