<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\Terbilang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TerbilangTest extends TestCase
{
    /**
     * @return array<string, array{string|int, string}>
     */
    public static function amounts(): array
    {
        return [
            'zero' => [0, 'Nol Rupiah'],
            'eleven' => [11, 'Sebelas Rupiah'],
            'nineteen' => [19, 'Sembilan Belas Rupiah'],
            'one hundred' => [100, 'Seratus Rupiah'],
            'one hundred eleven' => [111, 'Seratus Sebelas Rupiah'],
            'one thousand' => [1000, 'Seribu Rupiah'],
            'one thousand one' => [1001, 'Seribu Satu Rupiah'],
            'twenty-one thousand' => [21000, 'Dua Puluh Satu Ribu Rupiah'],
            'spp' => ['350000.00', 'Tiga Ratus Lima Puluh Ribu Rupiah'],
            'one and a half million' => [1500000, 'Satu Juta Lima Ratus Ribu Rupiah'],
            'two million' => [2000000, 'Dua Juta Rupiah'],
            'two billion' => [2000000000, 'Dua Miliar Rupiah'],
            'one thousand thousand' => [1001000, 'Satu Juta Seribu Rupiah'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_it_spells_whole_rupiah(string|int $amount, string $expected): void
    {
        $this->assertSame($expected, (new Terbilang)->rupiah($amount));
    }
}
