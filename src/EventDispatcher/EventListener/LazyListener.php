<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventDispatcher\EventListener;

use Psr\Container\ContainerInterface;

class LazyListener
{
    public function __construct(
        private readonly ContainerInterface $container,
        public readonly string $listener_class,
        public readonly string|null $listener_method = null,
    ) {
    }

    public function __invoke(object $event): void
    {
        $listener = $this->container->get($this->listener_class);

        if ($this->listener_method === null) {
            \assert(\is_callable($listener));
            $listener($event);
            return;
        }

        \assert(\method_exists($listener, $this->listener_method));
        $listener->{$this->listener_method}($event);
    }
}
