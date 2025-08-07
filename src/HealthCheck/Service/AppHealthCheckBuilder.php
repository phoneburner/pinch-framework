<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\Service;

use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Framework\HealthCheck\HealthCheckBuilder;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\Log\LoggerInterface;

class AppHealthCheckBuilder implements HealthCheckBuilder
{
    /**
     * @param array<ComponentHealthCheckService> $check_services
     */
    public function __construct(
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        private array $check_services = [],
        private string|null $description = '',
        private array $links = [],
    ) {
    }

    #[\Override]
    public function withServices(ComponentHealthCheckService ...$check_services): self
    {
        $this->check_services = $check_services;
        return $this;
    }

    #[\Override]
    public function withDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[\Override]
    public function withLinks(array $links): self
    {
        $this->links = $links;
        return $this;
    }

    #[\Override]
    public function make(): HealthCheck
    {
        try {
            $checks = [];
            foreach ($this->check_services as $check_service) {
                foreach ($check_service($this->clock) as $check) {
                    $checks[] = $check;
                }
            }

            return new HealthCheck(
                checks: $checks,
                links: $this->links,
                description: $this->description,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Health Check Factory Failure', [
                'exception' => $e,
            ]);

            return new HealthCheck(
                status: HealthStatus::Fail,
                checks: [],
                links: $this->links,
                description: $this->description,
            );
        }
    }
}
