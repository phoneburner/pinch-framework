<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\Domain;

class HealthCheck implements \JsonSerializable
{
    public readonly HealthStatus $status;

    /**
     * @var array<string, array<ComponentHealthCheck>>
     */
    public readonly array $checks;

    /**
     * @param HealthStatus|null $status Overrides the status, otherwise based on the component checks
     * @param array<ComponentHealthCheck> $checks
     */
    public function __construct(
        HealthStatus|null $status = null,
        public readonly string|null $version = null,
        public readonly string|null $release_id = null,
        public readonly array $notes = [],
        public readonly string|null $output = null,
        array $checks = [],
        public readonly array $links = [],
        public readonly string|null $service_id = null,
        public readonly string|null $description = null,
    ) {
        $component_status = 0b00;
        $component_checks = [];
        foreach ($checks as $check) {
            $component_status |= match ($check->status) {
                HealthStatus::Warn => 0b01,
                HealthStatus::Fail => 0b10,
                default => 0b00,
            };
            $component_checks[$check->name()][] = $check;
        }

        $this->checks = $component_checks;
        $this->status = $status ?? match (true) {
            (bool)($component_status & 0b10) => HealthStatus::Fail,
            (bool)($component_status & 0b01) => HealthStatus::Warn,
            default => HealthStatus::Pass,
        };
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'status' => $this->status,
            'version' => $this->version,
            'releaseId' => $this->release_id,
            'notes' => $this->notes ?: null,
            'output' => $this->output,
            'checks' => $this->checks ?: null,
            'links' => $this->links ?: null,
            'serviceId' => $this->service_id,
            'description' => $this->description,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
