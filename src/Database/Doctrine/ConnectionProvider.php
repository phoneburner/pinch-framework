<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\ConnectionNotFound;
use Doctrine\DBAL\Tools\Console\ConnectionProvider as DoctrineConnectionProvider;
use Psr\Container\ContainerInterface;

class ConnectionProvider implements DoctrineConnectionProvider
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    #[\Override]
    public function getDefaultConnection(): Connection
    {
        return $this->container->get(Connection::class);
    }

    #[\Override]
    public function getConnection(string $name = ConnectionFactory::DEFAULT): Connection
    {
        return match ($name) {
            ConnectionFactory::DEFAULT => $this->container->get(Connection::class),
            default => throw new ConnectionNotFound('Unknown Connection: ' . $name),
        };
    }
}
