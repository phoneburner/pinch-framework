<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck\Domain;

class MeasurementName
{
    public const string CONNECTIONS = 'connections';
    public const string RESPONSE_TIME = 'responseTime';
    public const string UTILIZATION = 'utilization';
    public const string UPTIME = 'uptime';
    public const string MESSAGES = 'messages';
}
