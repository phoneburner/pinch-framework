<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Redis;

use PhoneBurner\Pinch\Framework\Database\Config\RedisConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Config\RedisConnectionConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Redis\Exception\RedisConnectionFailure;
use Redis;
use RedisException;

class CachingRedisManager implements RedisManager
{
    private array $connections = [];

    public function __construct(
        private readonly RedisConfigStruct $config,
    ) {
    }

    #[\Override]
    public function connect(string $connection = self::DEFAULT): Redis
    {
        return $this->connections[$connection] ??= $this->doConnect($connection);
    }

    private function doConnect(string $connection): Redis
    {
        $connection_config = $this->config->connections[$connection] ?? throw new RedisConnectionFailure('Connection Not Found');
        \assert($connection_config instanceof RedisConnectionConfigStruct);

        try {
            $client = new Redis();
            match ($connection_config->persistent) {
                true => $client->pconnect(
                    $connection_config->host,
                    $connection_config->port,
                    $connection_config->timeout,
                    $connection,
                ),
                false => $client->connect(
                    $connection_config->host,
                    $connection_config->port,
                    $connection_config->timeout,
                ),
            } ?: throw new RedisConnectionFailure('Unable to Connect');
        } catch (RedisException $e) {
            throw new RedisConnectionFailure('Unable to Connect: ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $client;
    }
}
