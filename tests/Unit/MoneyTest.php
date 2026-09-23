<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_percent_of_uses_basis_points_and_rounds_down()
    {
        $this->assertSame(15_000, Money::percentOf(150_000, 1000));   // 10%
        $this->assertSame(5_000, Money::percentOf(100_000, 500));     // 5%
        $this->assertSame(3, Money::percentOf(33, 1000));             // 3.3 → 3
        $this->assertSame(0, Money::percentOf(100_000, 0));
    }

    public function test_percent_of_rejects_negative_rates()
    {
        $this->expectException(InvalidArgumentException::class);

        Money::percentOf(100, -1);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function takaInputs(): array
    {
        return [
            'whole' => ['1000', 100_000],
            'commas and decimals' => ['1,250.50', 125_050],
            'one decimal' => ['0.5', 50],
            'currency symbol' => ['৳25,000', 2_500_000],
            'negative' => ['-12.34', -1_234],
        ];
    }

    #[DataProvider('takaInputs')]
    public function test_from_taka_parses_to_poysha(string $input, int $expected)
    {
        $this->assertSame($expected, Money::fromTaka($input));
    }

    public function test_from_taka_rejects_sub_poysha_precision()
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromTaka('1.005');
    }

    public function test_format_renders_taka()
    {
        $this->assertSame('৳1,250.50', Money::format(125_050));
        $this->assertSame('-৳0.05', Money::format(-5));
        $this->assertSame('25,000.00', Money::format(2_500_000, symbol: false));
    }
}
