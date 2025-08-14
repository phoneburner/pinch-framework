<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\ConsoleRunner as MigrationConsoleRunner;
use Doctrine\ORM\Tools\Console\ConsoleRunner as OrmConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory;
use PhoneBurner\Pinch\Component\Configuration\Context;
use PhoneBurner\Pinch\Framework\Database\Config\DoctrineConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Config\DoctrineConnectionConfigStruct;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyDispatcherInterface;

use function PhoneBurner\Pinch\ghost;
use function PhoneBurner\Pinch\proxy;
use function PhoneBurner\Pinch\Type\narrow;

class ConsoleApplicationServiceFactory implements ServiceFactory
{
    private const string APP_NAME = "Pinch Command Line Console";

    public function __invoke(App $app, string $id): ConsoleApplication
    {
        $doctrine_config = narrow(DoctrineConfigStruct::class, $app->config->get('database.doctrine'));
        $default_connection = $doctrine_config->connections[$doctrine_config->default_connection];
        \assert($default_connection instanceof DoctrineConnectionConfigStruct);

        $application = new ConsoleApplication();
        $application->setName(self::APP_NAME);
        $application->setDispatcher($app->get(SymfonyDispatcherInterface::class));
        $application->setCommandLoader($app->get(CommandLoaderInterface::class));
        $application->setAutoExit(false); // allow the CliKernel to handle exit
        $application->setCatchExceptions($app->environment->context !== Context::Http);
        $application->setCatchErrors(false);

        $connection_loader = ghost(fn(ExistingConnection $ghost): null => $ghost->__construct($app->get(Connection::class)));
        $dependency_factory = proxy(static fn(DependencyFactory $proxy): DependencyFactory => DependencyFactory::fromConnection(
            new ConfigurationArray([
                'table_storage' => $default_connection->migrations->table_storage,
                'migrations_paths' => $default_connection->migrations->migrations_paths,
            ]),
            $connection_loader,
            $app->get(LoggerInterface::class),
        ));

        /** @phpstan-ignore staticMethod.internalClass */
        MigrationConsoleRunner::addCommands($application, $dependency_factory);
        OrmConsoleRunner::addCommands($application, $app->get(EntityManagerProvider::class));

        return $application;
    }
}
