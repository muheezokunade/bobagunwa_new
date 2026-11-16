<?php

namespace App\Support;

/**
 * RFC 6238 time-based one-time passwords (HMAC-SHA1, 6 digits, 30 s), the
 * format used by free authenticator apps. No third-party library or service.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $base32Secret, int $step, int $digits = 6): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $step), self::base32Decode($base32Secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function currentStep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    /** Returns the matching time step (±1 step clock drift) or null. */
    public static function match(string $base32Secret, string $code, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now = self::currentStep($time);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if (hash_equals(self::code($base32Secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period='.self::PERIOD;
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=-]+/', '', $text));
        $bits = '';
        foreach (str_split($text) as $c) {
            $pos = strpos(self::ALPHABET, $c);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 secret.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
