<?php

namespace App\Services\Finance;

/**
 * Indonesian number words for whole rupiah amounts, used on receipts.
 *
 * Casing convention: Title Case with a trailing "Rupiah", e.g. 350000 becomes
 * "Tiga Ratus Lima Puluh Ribu Rupiah". Decimal strings ("350000.00") are accepted;
 * the fractional part is ignored because fees are whole rupiah.
 */
class Terbilang
{
    private const UNITS = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

    private const SCALES = [
        1_000_000_000_000 => 'Triliun',
        1_000_000_000 => 'Miliar',
        1_000_000 => 'Juta',
    ];

    public function rupiah(string|int $amount): string
    {
        $number = (int) explode('.', (string) $amount)[0];

        return ($number === 0 ? 'Nol' : $this->words($number)).' Rupiah';
    }

    private function words(int $number): string
    {
        if ($number < 12) {
            return self::UNITS[$number];
        }

        if ($number < 20) {
            return self::UNITS[$number - 10].' Belas';
        }

        if ($number < 100) {
            return trim(self::UNITS[intdiv($number, 10)].' Puluh '.self::UNITS[$number % 10]);
        }

        if ($number < 200) {
            return trim('Seratus '.$this->words($number - 100));
        }

        if ($number < 1000) {
            return trim(self::UNITS[intdiv($number, 100)].' Ratus '.$this->words($number % 100));
        }

        if ($number < 2000) {
            return trim('Seribu '.$this->words($number - 1000));
        }

        if ($number < 1_000_000) {
            return trim($this->words(intdiv($number, 1000)).' Ribu '.$this->words($number % 1000));
        }

        foreach (self::SCALES as $divisor => $name) {
            if ($number >= $divisor) {
                return trim($this->words(intdiv($number, $divisor)).' '.$name.' '.$this->words($number % $divisor));
            }
        }

        return '';
    }
}
