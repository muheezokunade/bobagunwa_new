<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;

/**
 * Naira/kobo conversion. Money is always integer kobo; decimal input is parsed
 * as a string and never passes through floating point.
 */
final class Money
{
    public const MAX_KOBO = 100_000_000_000; // ₦1,000,000,000.00 sanity ceiling per entry

    /** Parse "50,000", "₦20000.50" or "1234.5" into integer kobo. */
    public static function parse(string|int|null $input, bool $allowZero = false, bool $allowNegative = false): int
    {
        $value = trim(str_replace(['₦', ',', ' ', 'NGN', 'ngn'], '', (string) $input));
        $negative = false;
        if ($allowNegative && str_starts_with($value, '-')) {
            $negative = true;
            $value = substr($value, 1);
        }
        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new DomainRuleException('Enter an amount in naira, for example 50000 or 20000.50.');
        }
        $kobo = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
        if ($kobo === 0 && ! $allowZero) {
            throw new DomainRuleException('The amount must be greater than zero.');
        }
        if ($kobo > self::MAX_KOBO) {
            throw new DomainRuleException('The amount is larger than the system allows for a single entry.');
        }

        return $negative ? -$kobo : $kobo;
    }

    /** Format integer kobo as ₦50,000.00 (negative shown with a leading minus). */
    public static function format(?int $kobo): string
    {
        $kobo ??= 0;
        $sign = $kobo < 0 ? '−' : '';
        $abs = abs($kobo);

        return $sign.'₦'.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Plain decimal for CSV exports, e.g. 50000.00. */
    public static function decimal(?int $kobo): string
    {
        $kobo ??= 0;
        $abs = abs($kobo);

        return ($kobo < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
