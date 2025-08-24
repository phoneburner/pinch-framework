<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console\Command;

use Carbon\CarbonImmutable;
use Crell\AttributeUtils\ClassAnalyzer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\Cache\AppendOnlyCache;
use PhoneBurner\Pinch\Component\Cache\Cache;
use PhoneBurner\Pinch\Component\Cache\Lock\LockFactory;
use PhoneBurner\Pinch\Component\Configuration\Configuration;
use PhoneBurner\Pinch\Component\Configuration\Environment;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\Asymmetric;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\EncryptionKeyPair;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\SignatureKeyPair;
use PhoneBurner\Pinch\Component\Cryptography\Hash\HashAlgorithm;
use PhoneBurner\Pinch\Component\Cryptography\Hash\Hmac;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\String\Ciphertext;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SharedKey;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\Symmetric;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use PhoneBurner\Pinch\Component\PhoneNumber\AreaCode\AreaCode;
use PhoneBurner\Pinch\Component\PhoneNumber\DomesticPhoneNumber;
use PhoneBurner\Pinch\Component\PhoneNumber\E164;
use PhoneBurner\Pinch\Framework\Console\Config\ShellConfigStruct;
use PhoneBurner\Pinch\Framework\Database\Redis\RedisManager;
use PhoneBurner\Pinch\String\Encoding\ConstantTimeEncoder;
use PhoneBurner\Pinch\String\Encoding\Encoder;
use PhoneBurner\Pinch\String\Encoding\Encoding;
use PhoneBurner\Pinch\Time\Interval\TimeInterval;
use PhoneBurner\Pinch\Type\Reflect;
use PhoneBurner\Pinch\Uuid\Uuid;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psy\Configuration as PsyConfiguration;
use Psy\Shell;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

#[AsCommand(self::NAME, self::DESCRIPTION)]
class InteractivePinchShellCommand extends Command
{
    public const string NAME = 'shell';

    public const string DESCRIPTION = 'Interactive PHP REPL Shell (PsySH)';

    public const string DEFAULT_MESSAGE = <<<EOF
        Interactive PHP REPL Shell (PsySH) \r\nEnter "ls -l" to List Defined Variables or "exit" to Quit
        EOF;

    public const array DEFAULT_PSYSH_OPTIONS = [
        'commands' => [],
        'configDir' => APP_ROOT . '/build/psysh/config',
        'dataDir' => APP_ROOT . '/build/psysh/data',
        'defaultIncludes' => [],
        'eraseDuplicates' => true,
        'errorLoggingLevel' => \E_ALL,
        'forceArrayIndexes' => true,
        'historySize' => 0, // unlimited
        'runtimeDir' => APP_ROOT . '/build/psysh/tmp',
        'startupMessage' => self::DEFAULT_MESSAGE,
        'updateCheck' => 'never',
        'useBracketedPaste' => true,
        'verbosity' => \Psy\Configuration::VERBOSITY_NORMAL,
    ];

    public const array DEFAULT_SERVICES = [
        'app' => App::class,
        'append_only_cache' => AppendOnlyCache::class,
        'cache' => Cache::class,
        'class_analyzer' => ClassAnalyzer::class,
        'config' => Configuration::class,
        'connection' => Connection::class,
        'container' => App::class, // duplicates "app" for backwards compatibility
        'em' => EntityManagerInterface::class,
        'environment' => Environment::class,
        'event_dispatcher' => EventDispatcherInterface::class,
        'lock_factory' => LockFactory::class,
        'logger' => LoggerInterface::class,
        'natrium' => Natrium::class,
        'mailer' => MailerInterface::class,
        'message_bus' => MessageBus::class,
        'redis_manager' => RedisManager::class,
        'storage' => FilesystemOperator::class,

    ];

    /**
     * Note, if a service has the same basename as a PHP function, the aliasing
     * will override that function name. E.g. `Hash::class` is problematic because it
     * conflicts with the built-in `hash()` function.
     */
    public const array DEFAULT_IMPORTS = [
        AreaCode::class,
        CarbonImmutable::class,
        DomesticPhoneNumber::class,
        E164::class,
        Reflect::class,
        Uuid::class,
        Psr7::class,
        SharedKey::class,
        Ciphertext::class,
        EncryptionKeyPair::class,
        SignatureKeyPair::class,
        Natrium::class,
        Encoding::class,
        Encoder::class,
        ConstantTimeEncoder::class,
        HashAlgorithm::class,
        Hmac::class,
        Symmetric::class,
        Asymmetric::class,
        TimeInterval::class,
    ];

    public function __construct(
        private readonly ShellConfigStruct $config,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct(self::NAME);
        $this->setDescription(self::DESCRIPTION);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shell = new Shell(new PsyConfiguration(\array_merge(self::DEFAULT_PSYSH_OPTIONS, $this->config->options)));
        $shell->setScopeVariables(\array_map(
            $this->container->get(...),
            \array_unique(\array_merge(self::DEFAULT_SERVICES, $this->config->services)),
        ));

        foreach (\array_unique(\array_merge(self::DEFAULT_IMPORTS, $this->config->imports)) as $import) {
            $shell->addCode(\sprintf('use %s;', $import), true);
        }

        return $shell->run();
    }
}
