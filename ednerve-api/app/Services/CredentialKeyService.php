<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Loads / generates the ECDSA P-256 keypair used to sign credentials.
 * Private key lives on disk mode 0600; public key world-readable.
 *
 * Key generation prefers native openssl functions; falls back to the
 * openssl CLI (XAMPP bundle, or Linux/macOS when native export is broken
 * by a missing OPENSSL_CONF).
 */
class CredentialKeyService
{
    private string $privatePath;
    private string $publicPath;

    public function __construct()
    {
        $this->privatePath = env('ECDSA_PRIVATE_KEY_PATH', storage_path('keys/ecdsa-private.pem'));
        $this->publicPath = env('ECDSA_PUBLIC_KEY_PATH', storage_path('keys/ecdsa-public.pem'));
    }

    public function generate(): array
    {
        File::ensureDirectoryExists(dirname($this->privatePath), 0700, true);

        if (File::exists($this->privatePath)) {
            @chmod($this->privatePath, 0600);
        }

        // Path 1: native openssl (works on standard Linux/Mac PHP builds)
        $native = $this->tryNativeKeygen();
        if ($native !== null) {
            File::put($this->privatePath, $native['private']);
            chmod($this->privatePath, 0600);
            File::put($this->publicPath, $native['public']);
            chmod($this->publicPath, 0644);
            return $native;
        }

        // Path 2: openssl CLI fallback (XAMPP Windows bundle)
        $cli = $this->tryCliKeygen();
        if ($cli !== null) {
            chmod($this->privatePath, 0600);
            chmod($this->publicPath, 0644);
            return $cli;
        }

        throw new \RuntimeException(
            'Could not generate ECDSA keypair. ' .
            'Native openssl_pkey_new failed (often due to a missing/invalid OPENSSL_CONF on Windows/XAMPP) ' .
            'and no openssl CLI binary was found on PATH or in common XAMPP locations. ' .
            'Set OPENSSL_CONF before starting PHP (e.g., in your Apache vhost or shell) and retry.'
        );
    }

    public function privateKeyPem(): string
    {
        if (!File::exists($this->privatePath)) {
            $this->generate();
        }
        return File::get($this->privatePath);
    }

    public function publicKeyPem(): string
    {
        if (!File::exists($this->publicPath)) {
            $this->generate();
        }
        return File::get($this->publicPath);
    }

    public function publicKeyFingerprint(): string
    {
        return strtoupper(implode(':', str_split(hash('sha256', $this->publicKeyPem()), 2)));
    }

    /* =========================
     |  Key generation paths
     |========================= */

    private function tryNativeKeygen(): ?array
    {
        $config = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $configFile = $this->opensslConfig();
        if ($configFile !== '') {
            $config['config'] = $configFile;
        }

        $resource = @openssl_pkey_new($config);
        if (!$resource) {
            return null;
        }

        $private = '';
        if (@openssl_pkey_export($resource, $private) !== true) {
            return null;
        }

        $public = openssl_pkey_get_details($resource)['key'] ?? null;
        if (!$public) {
            return null;
        }

        return ['private' => $private, 'public' => $public];
    }

    private function tryCliKeygen(): ?array
    {
        $binary = $this->opensslBinary();
        if ($binary === null) {
            return null;
        }

        $tmp = sys_get_temp_dir();
        $priv = $tmp . DIRECTORY_SEPARATOR . 'ec256-private-' . bin2hex(random_bytes(6)) . '.pem';
        $pub = $tmp . DIRECTORY_SEPARATOR . 'ec256-public-' . bin2hex(random_bytes(6)) . '.pem';

        $env = 'OPENSSL_CONF=' . $this->opensslConfig();
        $gen = $this->run("{$binary} ecparam -name prime256v1 -genkey -noout -out " . escapeshellarg($priv), $env);
        if ($gen !== 0 || !is_file($priv)) {
            return null;
        }

        $pubgen = $this->run("{$binary} ec -in " . escapeshellarg($priv) . " -pubout -out " . escapeshellarg($pub) . " 2>nul", $env);
        if ($pubgen !== 0 || !is_file($pub)) {
            @unlink($priv);
            return null;
        }

        $private = file_get_contents($priv);
        $public = file_get_contents($pub);
        @unlink($priv);
        @unlink($pub);

        if (!$private || !$public) {
            return null;
        }

        File::put($this->privatePath, $private);
        File::put($this->publicPath, $public);

        return ['private' => $private, 'public' => $public];
    }

    private function run(string $cmd, ?string $envPrefix): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $prefix = $envPrefix ? ('set ' . $envPrefix . '&& ') : '';
            $cmd = $prefix . $cmd;
            exec($cmd, $_, $code);
            return $code;
        }

        $cmd = ($envPrefix ? ($envPrefix . ' ') : '') . $cmd;
        exec($cmd, $_, $code);
        return $code;
    }

    /**
     * Locate an openssl binary on this machine.
     */
    private function opensslBinary(): ?string
    {
        // PATH first
        exec('openssl version 2>' . (PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null') . ' >' . (PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null'), $_, $code);
        if ($code === 0) {
            return 'openssl';
        }

        // Common XAMPP Windows locations
        foreach ([
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'C:\\xampp\\php\\openssl.exe',
            'C:\\xampp\\php\\extras\\ssl\\openssl.exe',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Linux
        foreach (['/usr/bin/openssl', '/usr/local/bin/openssl'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Locate the OpenSSL config path (Linux default or Windows/XAMPP path).
     */
    private function opensslConfig(): string
    {
        $candidates = [
            '/usr/lib/ssl/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
            '/etc/ssl/certs/openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\php\\openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
