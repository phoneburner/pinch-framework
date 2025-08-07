<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus;

use Monolog\ResettableInterface;
use PhoneBurner\Pinch\Collections\WeakSet;
use PhoneBurner\Pinch\Container\ResettableService;
use Symfony\Contracts\Service\ResetInterface;

class LongRunningProcessServiceResetter
{
    /**
     * Use a WeakSet to avoid holding references to services that would otherwise
     * be garbage collected.
     *
     * @var WeakSet<ResetInterface|ResettableInterface|ResettableService>
     */
    private readonly WeakSet $services;

    public function __construct()
    {
        $this->services = new WeakSet();
    }

    public function add(ResetInterface|ResettableInterface|ResettableService $service): void
    {
        $this->services->add($service);
    }

    public function remove(ResetInterface|ResettableInterface|ResettableService $service): void
    {
        $this->services->remove($service);
    }

    public function reset(): void
    {
        foreach ($this->services as $service) {
            $service->reset();
        }
    }
}
