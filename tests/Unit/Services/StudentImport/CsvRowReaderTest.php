<?php

namespace Tests\Unit\Services\StudentImport;

use App\Services\StudentImport\CsvRowReader;
use Tests\TestCase;

class CsvRowReaderTest extends TestCase
{
    public function test_reads_semicolon_delimited_files(): void
    {
        $reader = $this->reader("Nama;Kelas\nAyu;1A\n");

        $this->assertSame(['Nama', 'Kelas'], $reader->headers());
        $this->assertSame([['Ayu', '1A']], iterator_to_array($reader->rows()));
    }

    public function test_strips_a_utf8_bom(): void
    {
        $reader = $this->reader("\xEF\xBB\xBFNama;Kelas\nAyu;1A\n");

        $this->assertSame(['Nama', 'Kelas'], $reader->headers());
    }

    public function test_converts_windows_1252_to_utf8(): void
    {
        // "Müller" in Windows-1252.
        $reader = $this->reader("Nama\nM\xFCller\n");

        $this->assertSame(['Müller'], iterator_to_array($reader->rows())[0]);
    }

    public function test_keeps_leading_zeros_and_long_digit_strings(): void
    {
        $reader = $this->reader("NIS;NIK\n007;3175012345678901\n");

        $rows = iterator_to_array($reader->rows());

        $this->assertSame('007', $rows[0][0]);
        $this->assertSame('3175012345678901', $rows[0][1]);
    }

    public function test_trims_cells_and_skips_blank_lines(): void
    {
        $reader = $this->reader("Nama;Kelas\n  Ayu ;1A\n\nBudi;1B\n");

        $this->assertSame([
            ['Ayu', '1A'],
            ['Budi', '1B'],
        ], iterator_to_array($reader->rows()));
    }

    public function test_fingerprint_is_stable_for_equivalent_headers(): void
    {
        $first = $this->reader("Nama;Kelas\n");
        $second = $this->reader("Nama;Kelas\n");
        $third = $this->reader("Nama;Alamat\n");

        $this->assertSame($first->fingerprint(), $second->fingerprint());
        $this->assertNotSame($first->fingerprint(), $third->fingerprint());
    }

    private function reader(string $content): CsvRowReader
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return new CsvRowReader($stream);
    }
}
