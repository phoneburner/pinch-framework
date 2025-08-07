<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\AmqpTransportHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\MySqlHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\PhpRuntimeHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\ComponentHealthChecks\RedisHealthCheckService;
use PhoneBurner\Pinch\Framework\HealthCheck\Config\HealthCheckConfigStruct;
use PhoneBurner\Pinch\Framework\HealthCheck\RequestHandler\HealthCheckRequestHandler;
use PhoneBurner\Pinch\Framework\HealthCheck\RequestHandler\ReadyCheckRequestHandler;
use PhoneBurner\Pinch\Framework\HealthCheck\Service\AppHealthCheckBuilder;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;

use function PhoneBurner\Pinch\ghost;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class HealthCheckServiceProvider implements DeferrableServiceProvider
{
    public static function provides(): array
    {
        return [
            HealthCheckBuilder::class,
            HealthCheckRequestHandler::class,
            ReadyCheckRequestHandler::class,
            AmqpTransportHealthCheckService::class,
            MySqlHealthCheckService::class,
            PhpRuntimeHealthCheckService::class,
            RedisHealthCheckService::class,
        ];
    }

    public static function bind(): array
    {
        return [];
    }

    #[\Override]
    public static function register(App $app): void
    {
         $app->set(
             HealthCheckBuilder::class,
             ghost(static fn(AppHealthCheckBuilder $ghost): null => $ghost->__construct(
                 $app->get(Clock::class),
                 $app->get(LoggerInterface::class),
                 \array_map($app->services->get(...), $app->get(HealthCheckConfigStruct::class)->services),
                 \trim($app->config->get('app.name') . ' API Health Check'),
             )),
         );

         $app->set(
             HealthCheckRequestHandler::class,
             ghost(static fn(HealthCheckRequestHandler $ghost): null => $ghost->__construct(
                 $app->get(HealthCheckBuilder::class),
                 $app->get(LoggerInterface::class),
             )),
         );

         $app->set(
             ReadyCheckRequestHandler::class,
             ghost(static fn(ReadyCheckRequestHandler $ghost): null => $ghost->__construct(
                 $app->get(LoggerInterface::class),
             )),
         );

         $app->set(
             AmqpTransportHealthCheckService::class,
             ghost(static fn(AmqpTransportHealthCheckService $ghost): null => $ghost->__construct(
                 $app->get(AmqpTransport::class),
                 $app->get(LogTrace::class),
                 $app->get(LoggerInterface::class),
             )),
         );

         $app->set(
             MySqlHealthCheckService::class,
             ghost(static fn(MySqlHealthCheckService $ghost): null => $ghost->__construct(
                 $app->get(Connection::class),
                 $app->get(LogTrace::class),
                 $app->get(LoggerInterface::class),
             )),
         );

        $app->set(
            PhpRuntimeHealthCheckService::class,
            ghost(static fn(PhpRuntimeHealthCheckService $ghost): null => $ghost->__construct(
                $app->get(LogTrace::class),
            )),
        );

        $app->set(
            RedisHealthCheckService::class,
            ghost(static fn(RedisHealthCheckService $ghost): null => $ghost->__construct(
                $app->get(RedisManager::class),
                $app->get(LogTrace::class),
                $app->get(LoggerInterface::class),
            )),
        );
    }
}
