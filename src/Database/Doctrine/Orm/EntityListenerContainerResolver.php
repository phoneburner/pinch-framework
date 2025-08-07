<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Orm;

use Doctrine\ORM\Mapping\EntityListenerResolver;
use Psr\Container\ContainerInterface;

class EntityListenerContainerResolver implements EntityListenerResolver
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    #[\Override]
    public function clear(string|null $class_name = null): void
    {
        throw new \LogicException('`clear` not supported by container resolver');
    }

    #[\Override]
    public function register(object $object): void
    {
        throw new \LogicException('`register` not supported by container resolver');
    }

    #[\Override]
    public function resolve(string $class_name): object
    {
        if (! \class_exists($class_name)) {
            throw new \LogicException('`resolve` requires a class name');
        }

        $class = new \ReflectionClass($class_name);
        return $this->container->get($class->getName());
    }
}
