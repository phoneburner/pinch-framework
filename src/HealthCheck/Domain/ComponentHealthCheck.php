<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\Domain;

use PhoneBurner\Pinch\Time\Standards\Rfc3339;

class ComponentHealthCheck implements \JsonSerializable
{
    public function __construct(
        public readonly string|null $component_name = null,
        public readonly string|null $measurement_name = null,
        public readonly string|null $component_id = null,
        public readonly string|null $component_type = null,
        public readonly mixed $observed_value = null,
        public readonly string|null $observed_unit = null,
        public readonly HealthStatus|null $status = null,
        public readonly array|null $affected_endpoints = [],
        public readonly \DateTimeImmutable|null $time = null,
        public readonly string|null $output = null,
        public readonly array|null $links = [],
        public readonly array $additional = [],
    ) {
    }

    public function name(): string
    {
        return \trim($this->component_name . ':' . $this->measurement_name, ':');
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'componentId' => $this->component_id,
            'componentType' => $this->component_type,
            'observedValue' => $this->observed_value,
            'observedUnit' => $this->observed_unit,
            'status' => $this->status,
            'affectedEndpoints' => $this->affected_endpoints ?: null,
            'time' => $this->time?->format(Rfc3339::DATETIME),
            'output' => $this->output,
            'links' => $this->links ?: null,
            ...$this->additional,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
