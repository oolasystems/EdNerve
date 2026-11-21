<?php

namespace App\Console\Commands;

use App\Services\CredentialKeyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CredentialKeyGenerate extends Command
{
    protected $signature = 'credentials:key:generate
        {--force : Overwrite existing keys}
        {--password : Passphrase-protect the private key (prompted)}';

    protected $description = 'Generate the ECDSA P-256 keypair used to sign academic credentials.';

    public function handle(CredentialKeyService $keys): int
    {
        $privatePath = env('ECDSA_PRIVATE_KEY_PATH', storage_path('keys/ecdsa-private.pem'));
        $publicPath = env('ECDSA_PUBLIC_KEY_PATH', storage_path('keys/ecdsa-public.pem'));

        if (File::exists($privatePath) && !$this->option('force')) {
            $this->error("Private key already exists at {$privatePath}. Use --force to rotate (invalidate old verifications unless you retain the old public key).");
            return self::FAILURE;
        }

        try {
            $keys->generate();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('ECDSA P-256 keypair generated.');
        $this->line('Private : ' . $privatePath);
        $this->line('Public  : ' . $publicPath);
        $this->line('Public key fingerprint : ' . $keys->publicKeyFingerprint());

        return self::SUCCESS;
    }
}
