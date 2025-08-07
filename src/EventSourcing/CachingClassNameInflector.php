<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing;

use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;

class CachingClassNameInflector implements ClassNameInflector
{
    public array $cache = [];

    public function __construct(
        private readonly ClassNameInflector $inflector = new DotSeparatedSnakeCaseInflector(),
    ) {
    }

    public function classNameToType(string $class_name): string
    {
        return $this->cache['class'][$class_name] ??= $this->inflector->classNameToType($class_name);
    }

    public function typeToClassName(string $event_type): string
    {
        return $this->cache['type'][$event_type] ??= $this->inflector->typeToClassName($event_type);
    }

    public function instanceToType(object $instance): string
    {
        return $this->cache['instance'][$instance::class] ??= $this->inflector->instanceToType($instance);
    }
}
