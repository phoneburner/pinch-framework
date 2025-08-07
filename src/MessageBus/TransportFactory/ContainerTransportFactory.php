<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class ContainerTransportFactory implements TransportFactory
{
    public const array DEFAULT_FACTORIES = [
        AmazonSqsTransport::class => AmazonSqsTransportFactory::class,
        AmqpTransport::class => AmqpTransportFactory::class,
        DoctrineTransport::class => DoctrineTransportFactory::class,
        InMemoryTransport::class => InMemoryTransportFactory::class,
        RedisTransport::class => RedisTransportFactory::class,
        SyncTransport::class => SyncTransportFactory::class,
    ];

    /**
     * @var array<class-string<TransportInterface>, class-string<TransportFactory>>
     */
    private array $factories;

    /**
     * @param array<class-string<TransportInterface>, class-string<TransportFactory>> $factories
     */
    public function __construct(
        private readonly ContainerInterface $container,
        array $factories = [],
    ) {
        $this->factories = \array_merge(self::DEFAULT_FACTORIES, $factories);
    }

    public function make(TransportConfigStruct $config): TransportInterface
    {
        $factory_class = $this->factories[$config->class] ?? throw new \UnexpectedValueException(
            'Unsupported Transport Class: ' . $config->class,
        );

        return $this->container->get($factory_class)->make($config);
    }
}
