<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel-compatible CSV reading and safe CSV export.
 */
final class Csv
{
    /**
     * @param  array<string, list<string>>  $aliases  canonical column => accepted header spellings
     * @return array{headers: list<string>, rows: list<array<string, string>>, unknown: list<string>}
     */
    public static function read(string $path, array $aliases, int $maxRows): array
    {
        $content = (string) file_get_contents($path);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            // Excel on Windows often saves CSV as Windows-1252.
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        if (str_contains($content, "\0")) {
            throw new DomainRuleException('The file is not a text CSV file.');
        }
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! $header || count(array_filter($header, fn ($h) => trim((string) $h) !== '')) === 0) {
            throw new DomainRuleException('The file has no header row. Use the template headings.');
        }
        $lookup = [];
        foreach ($aliases as $canonical => $names) {
            foreach (array_merge([$canonical], $names) as $name) {
                $lookup[self::key($name)] = $canonical;
            }
        }
        $map = [];
        $unknown = [];
        foreach ($header as $index => $name) {
            $k = self::key((string) $name);
            if ($k === '') {
                continue;
            }
            if (isset($lookup[$k])) {
                $map[$index] = $lookup[$k];
            } else {
                $unknown[] = trim((string) $name);
            }
        }
        $rows = [];
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= $maxRows) {
                throw new DomainRuleException("The file has more than {$maxRows} data rows. Split it into smaller files.");
            }
            $row = array_fill_keys(array_keys($aliases), '');
            foreach ($map as $index => $canonical) {
                $row[$canonical] = trim((string) ($cells[$index] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => array_values(array_unique($map)), 'rows' => $rows, 'unknown' => $unknown];
    }

    /** Accepts YYYY-MM-DD or DD/MM/YYYY (Nigerian day-first convention). */
    public static function date(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['!Y-m-d', '!d/m/Y', '!j/n/Y', '!d-m-Y', '!d.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format(ltrim($format, '!')) === $value && $date->year >= 1900 && $date->year <= 2100) {
                return $date;
            }
        }
        throw new DomainRuleException("“{$value}” is not a valid date. Use YYYY-MM-DD or DD/MM/YYYY.");
    }

    /**
     * Stream a CSV download. Cells that a spreadsheet could interpret as a
     * formula are prefixed with an apostrophe (CSV injection protection).
     *
     * @param  list<string>  $headers
     * @param  iterable<list<scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public static function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'".$value : $value;
    }

    private static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name, " \t\n\r\0\x0B\xEF\xBB\xBF")));
    }
}
