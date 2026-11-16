<?php

namespace App\Support;

/**
 * Normalisation used for duplicate detection. Values are compared after
 * normalisation; the originally entered text is stored separately.
 */
final class Normalize
{
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }

    /**
     * Nigerian-aware phone normalisation to E.164 digits without "+":
     * 08031234567, +234 803 123 4567 and 2348031234567 all become 2348031234567.
     */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && in_array($digits[0], ['7', '8', '9'], true)) {
            return '234'.$digits;
        }

        return $digits;
    }

    public static function isPlausiblePhone(?string $normalized): bool
    {
        return $normalized !== null && strlen($normalized) >= 10 && strlen($normalized) <= 15;
    }

    /** Lower-case, accent-folded, single-spaced name for duplicate hints. */
    public static function name(?string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
        $folded = class_exists(\Normalizer::class) ? \Normalizer::normalize($name, \Normalizer::FORM_D) : $name;
        $folded = preg_replace('/\p{Mn}+/u', '', (string) $folded);

        return mb_strtolower((string) $folded);
    }

    /** Collapse whitespace while preserving Yoruba diacritics. */
    public static function text(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : (\Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value);
    }
}
