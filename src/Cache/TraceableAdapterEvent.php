<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache;

class TraceableAdapterEvent
{
    public function __construct(
        public string $name = '',
        public array $trace = [],
        public float $start = 0.0,
        public float $end = 0.0,
        public array $result = [],
        public int $hits = 0,
        public int $misses = 0,
    ) {
    }
}
