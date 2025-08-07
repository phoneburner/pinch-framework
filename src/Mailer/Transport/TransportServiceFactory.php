<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Mailer\Transport;

use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory;
use PhoneBurner\Pinch\Framework\Mailer\Config\SendgridDriverConfigStruct;
use PhoneBurner\Pinch\Framework\Mailer\Config\SmtpDriverConfigStruct;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class TransportServiceFactory implements ServiceFactory
{
    public function __invoke(App $app, string $id): TransportInterface
    {
        $transport_driver = (string)$app->config->get('mailer.default_driver');
        $transport_config = $app->config->get('mailer.drivers.' . $transport_driver) ?? [];
        \assert($transport_config instanceof SmtpDriverConfigStruct);

        $dns = match (TransportDriver::tryFrom($transport_driver)) {
            TransportDriver::SendGrid => $this->makeSendGridTransportDns($transport_config),
            TransportDriver::Smtp => $this->makeSmptTransportDns($transport_config),
            TransportDriver::None => 'null://default',
            default => throw new \RuntimeException('Unknown/Unsupported mailer transport driver: ' . $transport_driver),
        };

        return Transport::fromDsn(
            dsn: $dns,
            dispatcher: $app->get(EventDispatcherInterface::class),
            logger: $app->get(LoggerInterface::class),
        );
    }

    private function makeSendGridTransportDns(mixed $config): string
    {
        if (! $config instanceof SendgridDriverConfigStruct) {
            throw new \RuntimeException('Invalid SendGrid configuration');
        }

        return \sprintf(
            'sendgrid+api://%s@default',
            $config->api_key ?? throw new \RuntimeException('Missing/Invalid SendGrid API key'),
        );
    }

    private function makeSmptTransportDns(mixed $config): string
    {
        if (! $config instanceof SmtpDriverConfigStruct) {
            throw new \RuntimeException('Invalid SMTP configuration');
        }

        return \sprintf(
            'smtp://%s:%s@%s:%s%s',
            $config->user ?: throw new \RuntimeException('Missing/Invalid SMTP Credentials: User'),
            \urlencode($config->password ?: throw new \RuntimeException('Missing/Invalid SMTP Credentials: Password')),
            $config->host ?: throw new \RuntimeException('Missing/Invalid SMTP Credentials: Host'),
            $config->port ?: throw new \RuntimeException('Missing/Invalid SMTP Credentials: Port'),
            $config->encryption ? '' : '?auto_tls=false',
        );
    }
}
