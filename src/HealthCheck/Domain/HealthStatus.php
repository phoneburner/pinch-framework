<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\Domain;

/**
 * Standardized health status values for API health checks
 *
 * @link https://datatracker.ietf.org/doc/html/draft-inadarei-api-health-check-06
 */
enum HealthStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
