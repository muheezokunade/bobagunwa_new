<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Presentation helpers: UTC storage, Africa/Lagos display. */
final class Format
{
    public static function dateTime(?CarbonInterface $value): string
    {
        return $value ? $value->copy()->timezone(config('boba.display_timezone'))->format('j M Y, g:i a') : '—';
    }

    public static function date(?CarbonInterface $value): string
    {
        return $value ? $value->format('j M Y') : '—';
    }

    public static function label(?string $value): string
    {
        return $value ? ucfirst(str_replace(['_', '.'], ' ', $value)) : '—';
    }
}
