<?php

namespace Tests\Unit;

use App\Exceptions\DomainRuleException;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function valid(): array
    {
        return [['50000', 5_000_000], ['50,000', 5_000_000], ['₦20,000.00', 2_000_000], ['0.01', 1], ['1234.5', 123_450], [' 7 ', 700], ['NGN 100', 10_000]];
    }

    #[DataProvider('valid')]
    public function test_parses_decimal_naira_to_integer_kobo(string $input, int $kobo): void
    {
        $this->assertSame($kobo, Money::parse($input));
    }

    public static function invalid(): array
    {
        return [['abc'], ['1.234'], ['-5'], ['0'], [''], ['1e5'], ['10000000000000']];
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_amounts(string $input): void
    {
        $this->expectException(DomainRuleException::class);
        Money::parse($input);
    }

    public function test_negative_only_when_allowed_and_formatting(): void
    {
        $this->assertSame(-1500, Money::parse('-15', false, true));
        $this->assertSame('₦50,000.00', Money::format(5_000_000));
        $this->assertSame('−₦0.05', Money::format(-5));
        $this->assertSame('30000.00', Money::decimal(3_000_000));
        $this->assertSame('-0.50', Money::decimal(-50));
    }

    public function test_no_floating_point_drift(): void
    {
        $this->assertSame(1_999_999_999, Money::parse('19999999.99'));
        $this->assertSame(10, Money::parse('0.1'));
    }
}
