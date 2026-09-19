<?php

namespace Tests\Unit\Services\StudentImport;

use App\Services\StudentImport\ColumnMapper;
use Tests\TestCase;

class ColumnMapperTest extends TestCase
{
    public function test_exact_aliases_map_to_canonical_fields(): void
    {
        $mapped = (new ColumnMapper)->map([
            'Nama Lengkap', 'TGL LHR', 'NIS', 'Kelas',
        ]);

        $this->assertSame([
            'full_name' => 'Nama Lengkap',
            'dob' => 'TGL LHR',
            'student_number' => 'NIS',
            'class' => 'Kelas',
        ], $mapped);
    }

    public function test_prefix_headers_map_to_their_field(): void
    {
        $mapped = (new ColumnMapper)->map(['Nama Lengkap Siswa']);

        $this->assertSame(['full_name' => 'Nama Lengkap Siswa'], $mapped);
    }

    public function test_fuzzy_headers_map_to_their_field(): void
    {
        $mapped = (new ColumnMapper)->map(['Nama Lengkab']);

        $this->assertSame(['full_name' => 'Nama Lengkab'], $mapped);
    }

    public function test_a_column_is_consumed_once_and_unmapped_are_absent(): void
    {
        $mapped = (new ColumnMapper)->map(['Nama', 'Nama Panggilan', 'Catatan']);

        $this->assertArrayHasKey('full_name', $mapped);
        $this->assertArrayHasKey('nickname', $mapped);
        $this->assertArrayNotHasKey('guardian_name', $mapped);
        $this->assertCount(2, $mapped);
    }

    public function test_guardian_aliases_map_individually(): void
    {
        $mapped = (new ColumnMapper)->map([
            'Nama Wali', 'No HP Wali', 'Pekerjaan Wali', 'Alamat Wali',
        ]);

        $this->assertSame([
            'guardian_name' => 'Nama Wali',
            'guardian_phone' => 'No HP Wali',
            'guardian_work' => 'Pekerjaan Wali',
            'guardian_address' => 'Alamat Wali',
        ], $mapped);
    }
}
