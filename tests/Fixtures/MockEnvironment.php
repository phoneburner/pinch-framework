<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures;

use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Component\Configuration\Environment;

class MockEnvironment implements Environment
{
    public function __construct(
        public readonly string $root,
        private array $server = [],
        private array $env = [],
        public BuildStage $stage = BuildStage::Production,
        public Context $context = Context::Test,
    ) {
    }

    public function hostname(): string
    {
        return 'localhost';
    }

    public function has(string $id): bool
    {
        return isset($this->server[$id]) || isset($this->env[$id]);
    }

    public function get(string $id): mixed
    {
        return $this->server[$id] ?? $this->env[$id] ?? null;
    }

    public function server(
        string $key,
        mixed $production = null,
        mixed $development = null,
        mixed $integration = null,
    ): \UnitEnum|string|int|float|bool|null {
        $value = self::cast($this->server[$key] ?? null) ?? $this->match($production, $development, $integration);
        \assert($value === null || \is_scalar($value) || $value instanceof \UnitEnum);
        return $value;
    }

    public function env(
        string $key,
        mixed $production = null,
        mixed $development = null,
        mixed $integration = null,
    ): \UnitEnum|string|int|float|bool|null {
        $value = self::cast($this->env[$key] ?? null) ?? $this->match($production, $development, $integration);
        \assert($value === null || \is_scalar($value) || $value instanceof \UnitEnum);
        return $value;
    }

    public function match(mixed $production, mixed $development = null, mixed $integration = null): mixed
    {
        return match ($this->stage) {
            BuildStage::Production => $production,
            BuildStage::Development => $development ?? $production,
            BuildStage::Integration => $integration ?? $development ?? $production,
        };
    }

    private static function cast(\UnitEnum|string|int|float|bool|null $value): \UnitEnum|string|int|float|bool|null
    {
        return \is_string($value) ? match (\strtolower($value)) {
            'true', 'yes', 'on' => true,
            'false', 'no', 'off' => false,
            'null', '' => null,
            '0' => 0,
            '1' => 1,
            default => \filter_var($value, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE)
                ?? \filter_var($value, \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE)
                ?? $value,
        } : $value;
    }
}
