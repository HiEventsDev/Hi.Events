<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Console;

use Illuminate\Console\Command;

class GenerateLicenceKeypairCommand extends Command
{
    protected $signature = 'ee:licence:keypair
        {--kid= : Key id to publish the public key under, e.g. 2026-a}
        {--private-key-out= : File to write the base64 private key to (must not exist)}';

    protected $description = 'Generate an Ed25519 keypair for signing Hi.Events licence keys';

    public function handle(): int
    {
        $kid = (string) $this->option('kid');
        $out = (string) $this->option('private-key-out');

        if ($kid === '' || $out === '') {
            $this->error('Both --kid and --private-key-out are required.');

            return self::FAILURE;
        }

        if (file_exists($out)) {
            $this->error("Refusing to overwrite $out.");

            return self::FAILURE;
        }

        $keypair = sodium_crypto_sign_keypair();

        if (file_put_contents($out, base64_encode(sodium_crypto_sign_secretkey($keypair)).PHP_EOL) === false) {
            $this->error("Could not write $out.");

            return self::FAILURE;
        }
        chmod($out, 0600);

        $this->info("Private key written to $out. Store it in a password manager and delete the file; it must never be committed.");
        $this->newLine();
        $this->line('Add this entry to TrustedKeys::KEYS in backend/ee/Licensing/TrustedKeys.php:');
        $this->line(sprintf("    '%s' => '%s',", $kid, base64_encode(sodium_crypto_sign_publickey($keypair))));

        return self::SUCCESS;
    }
}
