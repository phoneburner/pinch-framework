<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck;

use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthCheck;

interface HealthCheckBuilder
{
    public function withServices(ComponentHealthCheckService ...$check_services): self;

    public function withDescription(string $description): self;

    public function withLinks(array $links): self;

    public function make(): HealthCheck;
}
