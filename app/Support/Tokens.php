<?php

namespace App\Support;

/** High-entropy secrets. Only SHA-256 hashes are ever persisted. */
final class Tokens
{
    public static function generate(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Human-readable reference such as BOB-APP-2026-7K3Q9X (not a secret). */
    public static function reference(string $prefix, int $length = 6): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $prefix.'-'.now()->format('Y').'-'.$out;
    }
}
