<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Cache\Lock;

use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyNamedKey;
use PhoneBurner\Pinch\Framework\Cache\Lock\SymfonyNamedKeyFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SymfonyNamedKeyTest extends TestCase
{
    #[Test]
    public function aNamedKeyNameCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The name cannot be empty.');
        new SymfonyNamedKey('');
    }

    #[Test]
    public function aNamedKeyHasANameAndAKeyState(): void
    {
        $named_key = new SymfonyNamedKey('FooBarBaz');
        self::assertSame('FooBarBaz', $named_key->name);
        self::assertSame('named_key.FooBarBaz', (string)$named_key);
        self::assertSame('locks.FooBarBaz', (string)$named_key->key);
    }

    #[Test]
    public function aNamedKeyPrefixesTheKeyState(): void
    {
        $named_key = new SymfonyNamedKey('FooBarBaz');
        self::assertSame('locks.FooBarBaz', (string)$named_key->key);

        $named_key = new SymfonyNamedKey('locks.FooBarBaz');
        self::assertSame('locks.FooBarBaz', (string)$named_key->key);
    }

    #[Test]
    public function itSerializesAndUnserializesANamedKey(): void
    {
        $named_key = new SymfonyNamedKeyFactory()->make('FooBarBaz');

        self::assertEquals($named_key, \unserialize(\serialize($named_key)));
    }
}
