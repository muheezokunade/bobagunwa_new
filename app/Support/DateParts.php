<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Dates entered as day / month name / year (no browser date picker, whose
 * mm/dd or dd/mm order depends on the device locale). Combines the three
 * parts into one Y-m-d value before validation; an impossible date such as
 * 31 February becomes an invalid value so the normal date rule rejects it.
 */
class DateParts
{
    public const MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    public static function merge(Request $request, string $name): void
    {
        $day = trim((string) $request->input("{$name}_day"));
        $month = trim((string) $request->input("{$name}_month"));
        $year = trim((string) $request->input("{$name}_year"));
        if (! $request->has("{$name}_day") && ! $request->has("{$name}_month") && ! $request->has("{$name}_year")) {
            return; // the single-field form (or an API client) is used as before
        }
        if ($day === '' && $month === '' && $year === '') {
            $request->merge([$name => null]);

            return;
        }
        $valid = ctype_digit($day) && ctype_digit($month) && preg_match('/^\d{4}$/', $year) === 1
            && checkdate((int) $month, (int) $day, (int) $year);
        $request->merge([$name => $valid ? sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day) : 'invalid']);
    }
}
