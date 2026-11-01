<?php

return [
    'index' => [
        'title' => 'Guru',
        'description' => 'Data induk untuk penugasan wali kelas dan penautan akun login.',
        'create' => 'Buat guru',
        'search_placeholder' => 'Cari berdasarkan nama, NIP/NUPTK, atau telepon…',
        'empty' => 'Guru tidak ditemukan.',
        'columns' => [
            'teacher_number' => 'NIP/NUPTK',
        ],
    ],
    'create' => [
        'title' => 'Buat guru',
        'description' => 'Data induk untuk penugasan wali kelas. Akun login dapat ditautkan nanti.',
        'submit' => 'Buat guru',
    ],
    'edit' => [
        'head' => 'Ubah :name',
        'title' => 'Ubah guru',
        'description' => 'Guru yang menjadi wali kelas atau terhubung ke akun login tidak dapat dihapus.',
    ],
    'delete' => [
        'title' => 'Hapus guru',
        'description' => 'Guru :name akan dihapus. Guru yang menjadi wali kelas atau terhubung ke akun login tidak dapat dihapus.',
    ],
];
