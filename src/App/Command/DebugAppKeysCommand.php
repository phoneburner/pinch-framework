<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\Command;

use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\EncryptionKeyPair;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\EncryptionPublicKey;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\KeyPair;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\PublicKey;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\SignatureKeyPair;
use PhoneBurner\Pinch\Component\Cryptography\Asymmetric\SignaturePublicKey;
use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\Key;
use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\KeyChain;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\Paseto\Paserk;
use PhoneBurner\Pinch\Component\Cryptography\Symmetric\SharedKey;
use PhoneBurner\Pinch\String\Encoding\Encoding;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(self::NAME, self::DESCRIPTION)]
class DebugAppKeysCommand extends Command
{
    public const string NAME = 'debug:keys';

    public const string DESCRIPTION = 'Display the public keys used by the application.';

    public const string SECRET_TEMPLATE = "<comment>SEC: %s</comment>\nPUB: %s";

    public const string PUBLIC_TEMPLATE = "PUB: %s";

    private readonly KeyChain $key_chain;

    public function __construct(Natrium $natrium)
    {
        $this->key_chain = $natrium->keys;
        parent::__construct(self::NAME);
        $this->setDescription(self::DESCRIPTION);
        $this->addOption(
            name: 'secret',
            description: 'Display the shared key (symmetric) and secret key values (asymmetric).',
        );
        $this->addOption(
            name: 'encoding',
            shortcut: 'e',
            mode: InputOption::VALUE_REQUIRED,
            description: 'The encoding to use for the keys, one of "base64", "base64url", or "hex"',
            default: 'base64',
            suggestedValues: ['base64', 'base64url', 'hex'],
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $show_secrets = (bool)$input->getOption('secret');
        $encoding = match ($input->getOption('encoding') ?? 'base64') {
            'base64' => Encoding::Base64,
            'base64url' => Encoding::Base64Url,
            'hex' => Encoding::Hex,
            default => throw new \InvalidArgumentException('Invalid encoding.'),
        };

        // Display the default "app" shared, encryption, and signature keys
        $keys = [
            ['name' => 'app', 'key' => $this->key_chain->shared()],
            ['name' => 'app', 'key' => $this->key_chain->encryption()],
            ['name' => 'app', 'key' => $this->key_chain->signature()],
        ];

        // Collect any other keys in the keychain
        foreach ($this->key_chain as $name => $key) {
            $keys[] = ['name' => $name, 'key' => $key];
        }

        $io = new SymfonyStyle($input, $output);
        $output->write(\PHP_EOL);
        $this->displayKeys($keys, $io, $encoding, $show_secrets);
        $output->write(\PHP_EOL);
        return Command::SUCCESS;
    }

    private function displayKeys(
        array $keys,
        OutputInterface $output,
        Encoding $encoding,
        bool $show_secrets = false,
    ): void {
        $output->writeln('<comment>Application Key Chain</comment>');
        $output->writeln(\str_repeat('=', 96));

        $table = new Table($output);
        $table->setHeaders(['Name', 'Ops', 'Type', 'Key', 'PASERK ID']);

        $counter = 0;
        foreach ($keys as ['name' => $name, 'key' => $key]) {
            if (++$counter > 1) {
                $table->addRow(new TableSeparator());
            }
            $table->addRow([
                $name,
                $this->formatKeyOperation($key),
                $this->formatKeyType($key),
                $this->formatKeyMaterial($show_secrets, $key, $encoding),
                $this->formatKeyIdMaterial($key),
            ]);
        }

        $table->render();
        $output->write(\PHP_EOL);
    }

    private function formatKeyMaterial(bool $show_secrets, Key $key, Encoding $encoding): string
    {
        if ($key instanceof SharedKey) {
            return \sprintf(
                "<comment>%s</comment>",
                $show_secrets ? $key->export($encoding) : '<hidden>',
            );
        }

        if ($key instanceof PublicKey) {
            return \sprintf(
                self::PUBLIC_TEMPLATE,
                $key->public()->export($encoding),
            );
        }

        if ($key instanceof KeyPair) {
            return \sprintf(
                self::SECRET_TEMPLATE,
                $show_secrets ? $key->secret()->export($encoding) : '<hidden>',
                $key->public()->export($encoding),
            );
        }

        return 'unknown';
    }

    private function formatKeyIdMaterial(Key $key): string
    {
        return match (true) {
            $key instanceof SharedKey => (string)Paserk::lid($key),
            $key instanceof SignaturePublicKey => (string)Paserk::pid($key),
            $key instanceof SignatureKeyPair => \sprintf("%s\n%s", Paserk::sid($key), Paserk::pid($key)),
            $key instanceof KeyPair => "N/A\nN/A",
            default => 'N/A',
        };
    }

    private function formatKeyOperation(mixed $key): string
    {
        return match (true) {
            $key instanceof SignatureKeyPair, $key instanceof SignaturePublicKey => 'sig',
            $key instanceof SharedKey, $key instanceof EncryptionKeyPair, $key instanceof EncryptionPublicKey => 'enc',
            default => '???',
        };
    }

    private function formatKeyType(mixed $key): string
    {
        return match (true) {
            $key instanceof KeyPair => 'secret',
            $key instanceof SharedKey => 'shared',
            $key instanceof PublicKey => 'public',
            default => 'other',
        };
    }
}
