<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache\Lock;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Cache\Lock\LockFactory;
use PhoneBurner\Pinch\Time\TimeInterval\TimeInterval;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory as SymfonyLockFactory;

#[Internal]
class SymfonyLockFactoryAdapter implements LockFactory, LoggerAwareInterface
{
    public function __construct(
        private readonly SymfonyNamedKeyFactory $key_factory,
        private readonly SymfonyLockFactory $lock_factory,
    ) {
    }

    #[\Override]
    public function make(
        SymfonyNamedKey|\Stringable|string $key,
        TimeInterval $ttl = new TimeInterval(seconds: 300),
        bool $auto_release = true,
    ): SymfonyLockAdapter {
        return new SymfonyLockAdapter($this->lock_factory->createLockFromKey(
            $key instanceof SymfonyNamedKey ? $key->key : $this->key_factory->make($key)->key,
            $ttl->seconds,
            $auto_release,
        ));
    }

    #[\Override]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->lock_factory->setLogger($logger);
    }
}
