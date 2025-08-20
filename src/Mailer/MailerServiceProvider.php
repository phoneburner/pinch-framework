<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Mailer;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\DeferrableServiceProvider;
use PhoneBurner\Pinch\Component\EmailAddress\EmailAddress;
use PhoneBurner\Pinch\Component\Mailer\Mailer;
use PhoneBurner\Pinch\Framework\Mailer\Transport\TransportServiceFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;

use function PhoneBurner\Pinch\ghost;

/**
 * @codeCoverageIgnore
 */
#[Internal('Override Definitions in Application Service Providers')]
final class MailerServiceProvider implements DeferrableServiceProvider
{
    public static function provides(): array
    {
        return [
            Mailer::class,
            MailerInterface::class,
            TransportInterface::class,
            MessageHandler::class,
            MailerTestCommand::class,
        ];
    }

    public static function bind(): array
    {
        return [Mailer::class => SymfonyMailerAdapter::class];
    }

    #[\Override]
    public static function register(App $app): void
    {
        $app->ghost(SymfonyMailerAdapter::class, static fn(SymfonyMailerAdapter $ghost): null => $ghost->__construct(
            $app->get(MailerInterface::class),
            new EmailAddress($app->config->get('mailer.default_from_address')),
        ));

        $app->set(
            MailerInterface::class,
            static fn(App $app): SymfonyMailer => match ((bool)$app->config->get('mailer.async')) {
                true => ghost(static fn(SymfonyMailer $ghost): null => $ghost->__construct(
                    $app->get(TransportInterface::class),
                    $app->get(MessageBusInterface::class),
                    $app->get(EventDispatcherInterface::class),
                )),
                false => ghost(static fn(SymfonyMailer $ghost): null => $ghost->__construct(
                    $app->get(TransportInterface::class),
                )),
            },
        );

        $app->ghost(MessageHandler::class, static fn(MessageHandler $ghost): null => $ghost->__construct(
            $app->get(TransportInterface::class),
        ));

        $app->set(TransportInterface::class, new TransportServiceFactory());

        $app->ghost(MailerTestCommand::class, static fn(MailerTestCommand $ghost): null => $ghost->__construct(
            $app->get(TransportInterface::class),
        ));
    }
}
