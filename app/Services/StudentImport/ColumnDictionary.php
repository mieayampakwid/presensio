<?php

namespace App\Services\StudentImport;

/**
 * Seeded Indonesian/English alias dictionary for import column headers. The
 * keys are the canonical field names; the values are lowercase aliases a
 * school is likely to use (matched after normalization).
 */
class ColumnDictionary
{
    /**
     * Canonical field => alias list. Field semantics:
     * full_name (required), class (required to map), the rest optional.
     *
     * @return array<string, list<string>>
     */
    public static function aliases(): array
    {
        return [
            'full_name' => [
                'nama lengkap', 'nama', 'name', 'full name', 'nama siswa',
                'nama murid', 'nama peserta didik',
            ],
            'nickname' => [
                'nama panggilan', 'panggilan', 'nickname', 'nick name',
            ],
            'dob' => [
                'tgl lahir', 'tanggal lahir', 'tgl lhr', 'tanggal kelahiran',
                'birth date', 'date of birth', 'dob', 'tgl. lahir',
            ],
            'student_number' => [
                'nis', 'no induk', 'nomor induk siswa', 'student number',
                'no. induk', 'nisn',
            ],
            'class' => [
                'kelas', 'class', 'rombel', 'kelas rombel', 'kelas saat ini',
            ],
            'guardian_name' => [
                'nama wali', 'wali', 'nama orang tua', 'orang tua',
                'parent name', 'nama orang tua/wali', 'nama wali murid',
            ],
            'guardian_phone' => [
                'telp wali', 'no hp wali', 'hp wali', 'telepon wali',
                'wa wali', 'no wa wali', 'nomor hp wali', 'phone',
                'phone number', 'no hp orang tua', 'telp orang tua',
            ],
            'guardian_work' => [
                'pekerjaan wali', 'pekerjaan', 'occupation', 'job',
                'pekerjaan orang tua',
            ],
            'guardian_address' => [
                'alamat wali', 'alamat', 'address', 'alamat orang tua',
                'alamat lengkap',
            ],
        ];
    }
}
