<?php

namespace Tests\Unit;

use App\Exceptions\DomainRuleException;
use App\Support\Csv;
use App\Support\Normalize;
use PHPUnit\Framework\TestCase;

class NormalizeAndCsvTest extends TestCase
{
    public function test_nigerian_phone_numbers_normalise_to_one_form(): void
    {
        foreach (['08031234567', '+234 803 123 4567', '2348031234567', '(0803) 123-4567', '8031234567'] as $phone) {
            $this->assertSame('2348031234567', Normalize::phone($phone), $phone);
        }
        $this->assertNull(Normalize::phone('  '));
    }

    public function test_names_fold_yoruba_diacritics_for_duplicate_detection_only(): void
    {
        $this->assertSame('ola adebayo', Normalize::name('  Ọlá   Adébáyọ̀ '));
        $this->assertSame('Ọlá Adébáyọ̀', Normalize::text('  Ọlá   Adébáyọ̀ '));
    }

    public function test_csv_reads_excel_bom_semicolons_and_aliases(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBFName;Email Address;Unknown\nAdé Synthetic;a@example.test;x\n;;\n");
        $parsed = Csv::read($path, ['full_name' => ['name'], 'email' => ['email_address']], 10);
        $this->assertSame([['full_name' => 'Adé Synthetic', 'email' => 'a@example.test']], $parsed['rows']);
        $this->assertSame(['Unknown'], $parsed['unknown']);
        unlink($path);
    }

    public function test_csv_row_limit_is_enforced(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "full_name\nA\nB\nC\n");
        $this->expectException(DomainRuleException::class);
        try {
            Csv::read($path, ['full_name' => []], 2);
        } finally {
            unlink($path);
        }
    }

    public function test_dates_accept_iso_and_day_first_and_reject_impossible_dates(): void
    {
        $this->assertSame('1981-03-14', Csv::date('1981-03-14')->toDateString());
        $this->assertSame('1981-03-14', Csv::date('14/03/1981')->toDateString());
        $this->assertSame('1981-03-04', Csv::date('4/3/1981')->toDateString());
        $this->assertNull(Csv::date(''));
        $this->expectException(DomainRuleException::class);
        Csv::date('31/02/1981');
    }

    public function test_csv_injection_is_neutralised_in_exports(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", Csv::safeCell('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", Csv::safeCell('@SUM(A1)'));
        $this->assertSame('-5000.00', Csv::safeCell('-5000.00'));
        $this->assertSame('Plain', Csv::safeCell('Plain'));
    }
}
