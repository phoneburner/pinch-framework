<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection as RedisTransportConnection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;

use function PhoneBurner\Pinch\ghost;

class RedisTransportFactory implements TransportFactory
{
    public function __construct(
        private readonly RedisManager $redis_manager,
        private readonly Environment $environment,
    ) {
    }

    /**
     * @see RedisTransportConnection::DEFAULT_OPTIONS for available options
     */
    public function make(TransportConfigStruct $config): RedisTransport
    {
        \assert($config->class === RedisTransport::class);

        $options = $config->options;
        $options['consumer'] ??= $this->environment->hostname();
        return ghost(fn(RedisTransport $ghost): null => $ghost->__construct(
            new RedisTransportConnection($options, $this->redis_manager->connect($config->connection)),
        ));
    }
}
