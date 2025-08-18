<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;

use PhoneBurner\Pinch\Framework\Database\Doctrine\ConnectionProvider;
use PhoneBurner\Pinch\Framework\MessageBus\Config\TransportConfigStruct;
use PhoneBurner\Pinch\Framework\MessageBus\TransportFactory;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as DoctrineTransportConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

use function PhoneBurner\Pinch\ghost;

class DoctrineTransportFactory implements TransportFactory
{
    public const string DEFAULT_FAILED_MESSAGE_TABLE = 'message_bus_failed_messages';

    public function __construct(
        private readonly ConnectionProvider $connection_provider,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function make(TransportConfigStruct $config): DoctrineTransport
    {
        \assert($config->class === DoctrineTransport::class);

        $options = $config->options;
        $options['auto_setup'] = false; // disable auto setup
        return ghost(fn(DoctrineTransport $ghost): null => $ghost->__construct(
            /** @phpstan-ignore new.internalClass */
            new DoctrineTransportConnection(
                $options,
                $this->connection_provider->getConnection($config->connection),
            ),
            $this->serializer,
        ));
    }
}
