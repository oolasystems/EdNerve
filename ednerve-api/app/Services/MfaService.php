<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * RFC 6238 TOTP implementation (SHA-1, 30s step, 6 digits), self-contained.
 * Secrets are stored AES-encrypted at rest with APP_KEY (via Crypt).
 */
class MfaService
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** Generate a base32 secret of $bytes decoded bytes (default 20). */
    public function generateSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        return $this->base32Encode($raw);
    }

    /** Encrypted-at-rest storage of the secret. */
    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(string $stored): string
    {
        return Crypt::decryptString($stored);
    }

    /** Build the otpauth:// URI shown as QR code on enrolment. */
    public function otpauthUri(string $secret, string $issuer, string $account): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** Generate $count single-use recovery codes in XXXX-XXXX-XXXX form. */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(6))); // 12 hex chars
            $codes[] = implode('-', [
                substr($raw, 0, 4),
                substr($raw, 4, 4),
                substr($raw, 8, 4),
            ]);
        }
        return $codes;
    }

    /** Constant-time base32-encoded HMAC TOTP at a given timestamp. */
    public function totpCode(string $secret, int $timestamp): string
    {
        $time = (int) floor($timestamp / self::PERIOD);
        $key = $this->base32Decode($secret);
        $message = pack('NN', 0, $time);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Verify a 6-digit TOTP (± window periods for clock skew). */
    public function verifyTotp(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $expected = $this->totpCode($secret, $now + ($i * self::PERIOD));
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }

    private function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= self::BASE32[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $input): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
        if ($clean === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($clean) as $char) {
            $value = strpos(self::BASE32, $char);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }

        return $bytes;
    }
}
